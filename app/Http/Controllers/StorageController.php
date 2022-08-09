<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use phpseclib3\Crypt\RSA;

class StorageController extends Controller
{

    // https://stackoverflow.com/questions/11449577/why-is-base64-encode-adding-a-slash-in-the-result
    public static function base64_url_encode(string $str){
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($str));
    }

    public static function base64_url_decode($str) {
        return base64_decode(str_replace(array('-', '_'), array('+', '/'), $str));
    }
    public static function getFiles(Request $request)
    {
        $email = Repository::getEmailFromUserId(Auth::user()->id);
        $privateKey = RSA::loadPrivateKey(file_get_contents(base_path().'/privateKeys/'.$email .'/key.pem'));
        $allFiles = Repository::getFiles(Auth::user()->id);
        $sharedFiles = Repository::getSharedFiles(Auth::user()->id);
        $normal_files = [];
        $shared_files = [];
        foreach ($allFiles as $file) {
            array_push($normal_files, [
                'id' => $file->id,
                'name' => $privateKey->decrypt(StorageController::base64_url_decode($file->name)),
            ]);
        }
        foreach ($sharedFiles as $file) {
            array_push($shared_files, [
                'id' => $file->file_id,
                'owner'=> $file->owner_id,
                'friend' => $file->friend_id,
                'name' => $privateKey->decrypt(StorageController::base64_url_decode($file->name)),                
            ]);
        }
        $files = ['files' => $normal_files, 'sharedFiles' => $shared_files];
        return view('files.files', compact('files'));
    }

    public static function storeFile(Request $request)
    {   $file = $request->file('user_file');
        $email = Repository::getEmailFromUserId(Auth::user()->id);
        $privateKey = RSA::loadPrivateKey(file_get_contents(base_path().'/privateKeys/'.$email .'/key.pem'));
        $name = $file->getClientOriginalName();
        $name = StorageController::base64_url_encode($privateKey->getPublicKey()->encrypt($name));
        $content = base64_encode($privateKey->getPublicKey()->encrypt($file->getContent()));
        //dd('path :'. $path . ' name :' . $name);
        Repository::insertFile(Auth::user()->id, $name, $content);
        return redirect('files');
    }
    public static function downloadFile(Request $request)
    {
        $email = Repository::getEmailFromUserId(Auth::user()->id);
        $privateKey = RSA::loadPrivateKey(file_get_contents(base_path().'/privateKeys/'.$email .'/key.pem'));
        if($request->has('owner') && $request->has('friend')){
            // content is re-encrypted when file is shared so a different content has to be fetched when a file is shared
            $file = Repository::getSharedFile($request->owner, $request->friend, $request->id)->first();
            $name = $privateKey->decrypt(StorageController::base64_url_decode($file->name));
            $content = $privateKey->decrypt(base64_decode($file->content));
        }
        else{
        $file = Repository::getFile($request->id)->first();
        $name = $privateKey->decrypt(StorageController::base64_url_decode($file->name));
        $content = $privateKey->decrypt(base64_decode($file->content));
        }
        // Can't believe it https://stackoverflow.com/questions/34624118/working-with-encrypted-files-in-laravel-how-to-download-decrypted-file
        return response()->streamDownload(function() use($content) {
            echo $content;
        } ,$name);
       
        return redirect('files');
    }

    public static function removeFile(Request $request){
        $file = Repository::getFile($request->id)->first();        
        Repository::removeFile($file->id);
        return redirect('files');
    }

    public static function shareFile(Request $request){
        $owner = Auth::user()->id;
        $friend = Repository::getUserIdFromEmail($request->email);
        if($owner == $friend){
            return redirect('files')->with('message', "Can't share files with yourself.");
        }
        // friendships are annoyingly one way so both ways have to be checked, since it's sufficient
        $owner_friend_friendship = (Repository::getFriendship($owner, $friend)->first() == null) ? false : strcmp(Repository::getFriendship($owner, $friend)->first()->status, 'confirmed');
        $friend_owner_friendship = (Repository::getFriendship($friend, $owner)->first() == null) ? false : strcmp(Repository::getFriendship($friend, $owner)->first()->status, 'confirmed');
        $areFriends = $owner_friend_friendship || $friend_owner_friendship;
        $file = Repository::getFile($request->id)->first();
        if($areFriends == 0){ // string are equal when strcmp is 0 in PHP              
            if(!Repository::sharedFileRecordExists($owner, $friend, $file->id)){
                // Decrypting file name and file content for current user
                // And encrypting them with the pub key of the friend
                // Such that friend can decrypt with their own private key on their end
                $email = Repository::getEmailFromUserId(Auth::user()->id);
                $privateKey = RSA::loadPrivateKey(file_get_contents(base_path().'/privateKeys/'.$email .'/key.pem'));
                $decryptedName = $privateKey->decrypt(StorageController::base64_url_decode($file->name));
                $decryptedContent =$privateKey->decrypt(base64_decode($file->content));
                $friendPubKey = RSA::loadPublicKey(Repository::getUserPublicKey($friend));                
                $encryptedName = StorageController::base64_url_encode($friendPubKey->encrypt($decryptedName));
                $encryptedContent = base64_encode($friendPubKey->encrypt($decryptedContent));
                Repository::shareFileWithFriend($owner, $friend, $file->id, $encryptedName, $encryptedContent);
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
