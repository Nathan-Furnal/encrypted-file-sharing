<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ParagonIE\Halite\Asymmetric\Crypto as AsymmetricCrypto;
use ParagonIE\Halite\File;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\Stream\MutableFile;


class StorageController extends Controller
{
    private static $validExtensions = ['txt', 'csv', 'pdf', 'jpg', 'jpeg', 'png', 'docx'];

    private static function getUserPublicKey(int $user_id){
        $out = Repository::getUserPublicKey($user_id);
        return KeyFactory::importEncryptionPublicKey(new HiddenString($out));
    }

    private static function getUserPrivateKey(int $user_id){
        $email = Repository::getEmailFromUserId($user_id);
        return KeyFactory::loadEncryptionSecretKey(base_path().'/privateKeys/'.$email .'/key.pem');
    }

    public static function getFiles(Request $request)
    {
        $privateKey = StorageController::getUserPrivateKey(Auth::user()->id);
        $allFiles = Repository::getFiles(Auth::user()->id);
        $sharedFiles = Repository::getSharedFiles(Auth::user()->id);
        $normal_files = [];
        $shared_files = [];
        foreach ($allFiles as $file) {
        // Decrypt (unseal) the AES key, convert it to the proper string and load it as a key
            $keyAsString = AsymmetricCrypto::unseal($file->enc_key, $privateKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            array_push($normal_files, [
                'id' => $file->id,
                'name' => Crypto::decrypt($file->name, $enc_key)->getString(),
            ]);
        }
        foreach ($sharedFiles as $file) {
            $ownerPubKey = StorageController::getUserPublicKey($file->owner_id);
            $keyAsString = AsymmetricCrypto::decrypt($file->enc_key, $privateKey, $ownerPubKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            array_push($shared_files, [
                'id' => $file->file_id,
                'owner'=> $file->owner_id,
                'friend' => $file->friend_id,
                'name' =>  Crypto::decrypt(Repository::getFile($file->file_id)->first()->name, $enc_key)->getString(),
            ]);
        }
        $files = ['files' => $normal_files, 'sharedFiles' => $shared_files];
        return view('files.files', compact('files'));
    }

    public static function storeFile(Request $request){
        $file = $request->file('user_file');
        if($file == null){
            return redirect('files')->with('message', "A file must be uploaded.");
        }
        if(!in_array($file->extension(), StorageController::$validExtensions)){
            return redirect('files')->with('message', 'Not a valid file extension, please use one of:  '.implode(', ', StorageController::$validExtensions));
        }
        // 1. Generate AES key
        // 2. Crypt the file and file name with it
        // 3. Store it sealed with the public key
        $enc_key = KeyFactory::generateEncryptionKey();
        $keyAsString = KeyFactory::export($enc_key)->getString();
        $cipheredName = Crypto::encrypt(new HiddenString($file->getClientOriginalName()), $enc_key);
        $pubKey = StorageController::getUserPublicKey(Auth::user()->id);
        $sealed = AsymmetricCrypto::seal(new HiddenString($keyAsString), $pubKey);
        File::encrypt($file, storage_path().'/app/'.$cipheredName, $enc_key);
        Repository::insertFile(Auth::user()->id, $cipheredName, $sealed);
        return redirect('files');
    }

    public static function downloadFile(Request $request)
    {
        $privateKey = StorageController::getUserPrivateKey(Auth::user()->id);
        if($request->has('owner') && $request->has('friend')){
            // content is re-encrypted when file is shared so a different content has to be fetched when a file is shared
            $sharedFile = Repository::getSharedFile($request->owner, $request->friend, $request->id)->first();
            $ownerPubKey = StorageController::getUserPublicKey($sharedFile->owner_id);
            $keyAsString = AsymmetricCrypto::decrypt($sharedFile->enc_key, $privateKey, $ownerPubKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            $file = Repository::getFile($request->id)->first();            
            $decipheredName = Crypto::decrypt($file->name, $enc_key)->getString();
        }
        else{
            $file = Repository::getFile($request->id)->first();
            $keyAsString = AsymmetricCrypto::unseal($file->enc_key, $privateKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);        
            $decipheredName = Crypto::decrypt($file->name, $enc_key)->getString();
        }
        $filepath = storage_path().'/app/'.$file->name;
        $stream = new MutableFile(fopen('php://output', 'wb'));
        ob_start();
        File::decrypt($filepath, $stream, $enc_key);
        // Can't believe it https://stackoverflow.com/questions/34624118/working-with-encrypted-files-in-laravel-how-to-download-decrypted-file
        return response()->streamDownload(function() {
            echo ob_get_clean();
        } ,$decipheredName);
        return redirect('files');
    }

    public static function removeFile(Request $request){
        $file = Repository::getFile($request->id)->first();
        Repository::removeFile($file->id);
        Storage::disk('local')->delete($file->name);
        return redirect('files');
    }

    public static function shareFile(Request $request){
        $owner_id = Auth::user()->id;
        $friend_id = Repository::getUserIdFromEmail($request->email);
        if($owner_id == $friend_id){
            return redirect('files')->with('message', "Can't share files with yourself.");
        }
        // friendships are annoyingly one way so both ways have to be checked, since it's sufficient to be friend one-way to share
        // string are equal when strcmp is 0 in PHP
        $owner_friend_friendship = (Repository::getFriendship($owner_id, $friend_id)->first() == null) ? false
                                 : strcmp(Repository::getFriendship($owner_id, $friend_id)->first()->status, 'confirmed') === 0;
        $friend_owner_friendship = (Repository::getFriendship($friend_id, $owner_id)->first() == null) ? false
                                 : strcmp(Repository::getFriendship($friend_id, $owner_id)->first()->status, 'confirmed') === 0;        
        $areFriends = $owner_friend_friendship || $friend_owner_friendship;
        $file = Repository::getFile($request->id)->first();
        if($areFriends){               
            if(!Repository::sharedFileRecordExists($owner_id, $friend_id, $file->id)){
                // Decrypting encryption key before sending it
                // And encrypting it with the pub key of the friend
                // Such that friend can decrypt with their own private key on their end
                $privateKey = StorageController::getUserPrivateKey(Auth::user()->id);
                $keyAsString = AsymmetricCrypto::unseal($file->enc_key, $privateKey);
                $friendPubKey = StorageController::getUserPublicKey($friend_id);
                $enc_for_friend = AsymmetricCrypto::encrypt($keyAsString, $privateKey, $friendPubKey);
                Repository::shareFileWithFriend($owner_id, $friend_id, $file->id, $enc_for_friend);
                return redirect('files')->with('message', 'File was shared successfully!');
            }
            else{
                return redirect('files')->with('message', 'File was already shared with this user.');
            }
        }
        else{
            return redirect('files')->with('message', 'No such friend');
        }
    }
}
