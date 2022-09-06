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

/**
 * Provides the main controller for interaction between the user facing views and the storage.
 * Note that several choices were made for ease of use and demonstration but are ill-advised security-wise;
 * the users' private keys should NOT live on the server or be server accessible in any shape or form.
 * Also, the keys used for encrypting and signing are different.
 */
class StorageController extends Controller
{
    /**
     * @var string[] a group of valid extensions for upload.
     */
    private static $validExtensions = ['txt', 'csv', 'pdf', 'jpg', 'jpeg', 'png', 'docx', 'org'];

    /** Gets the public encryption key of the user.
     * @param int $user_id
     * @return \ParagonIE\Halite\Asymmetric\EncryptionPublicKey
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \SodiumException
     */
    private static function getUserPublicKey(int $user_id)
    {
        $out = Repository::getUserPublicKey($user_id);
        return KeyFactory::importEncryptionPublicKey(new HiddenString($out));
    }

    /** Gets the public signature key of the user.
     * @param int $user_id
     * @return \ParagonIE\Halite\Asymmetric\SignaturePublicKey
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \SodiumException
     */
    private static function getUserPublicSignatureKey(int $user_id)
    {
        $out = Repository::getUserPublicSignKey($user_id);
        return KeyFactory::importSignaturePublicKey(new HiddenString($out));
    }

    /** Gets the private encryption key of the user. Use with caution, the private key should never be exposed.
     * @param int $user_id
     * @return \ParagonIE\Halite\Asymmetric\EncryptionSecretKey
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \SodiumException
     */
    private static function getUserPrivateKey(int $user_id)
    {
        $email = Repository::getEmailFromUserId($user_id);
        return KeyFactory::loadEncryptionSecretKey(base_path() . '/privateKeys/' . $email . '/key.pem');
    }

    /** Gets the private signature key of the user. Use with caution, the private key should never be exposed.
     * @param int $user_id
     * @return \ParagonIE\Halite\Asymmetric\SignatureSecretKey
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \SodiumException
     */
    private static function getUserPrivateSignatureKey(int $user_id)
    {
        $email = Repository::getEmailFromUserId($user_id);
        return KeyFactory::loadSignatureSecretKey(base_path() . '/privateKeys/' . $email . '/sign.pem');
    }

    /** Gets all the files a user is allowed to see, from the DB. In practice, the files are no exposed and only their names
     * are decrypted to be shown to the user.
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \ParagonIE\Halite\Alerts\InvalidMessage
     * @throws \ParagonIE\Halite\Alerts\InvalidSignature
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \SodiumException
     */
    public static function getFiles(Request $request)
    {
        $privateKey = StorageController::getUserPrivateKey(Auth::user()->id);
        $allFiles = Repository::getFiles(Auth::user()->id);
        $sharedFiles = Repository::getSharedFiles(Auth::user()->id);
        $normal_files = [];
        $shared_files = [];
        foreach ($allFiles as $file) {
            // Decrypt (unseal) the symmetric key, convert it to the proper string and load it as a key
            $keyAsString = AsymmetricCrypto::unseal($file->enc_key, $privateKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            $normal_files[] = [
                'id' => $file->id,
                'name' => Crypto::decrypt($file->name, $enc_key)->getString(),
            ];
        }
        foreach ($sharedFiles as $file) {
            $ownerPubKey = StorageController::getUserPublicKey($file->owner_id);
            $keyAsString = AsymmetricCrypto::decrypt($file->enc_key, $privateKey, $ownerPubKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            $shared_files[] = [
                'id' => $file->file_id,
                'owner' => $file->owner_id,
                'friend' => $file->friend_id,
                'name' => Crypto::decrypt(Repository::getFile($file->file_id)->first()->name, $enc_key)->getString(),
            ];
        }
        $files = ['files' => $normal_files, 'sharedFiles' => $shared_files];
        return view('files.files', compact('files'));
    }

    /** Stores a file in local storage. It first checks that a file does exist and that its extension is valid.
     * Then, a symmetric encryption key generated and used to encrypt both the file and the file name. Prior to that,
     * the file is signed with asymmetric encryption using a different key than the one used to encrypt the symmetric encryption key.
     * Next, the symmetric encryption key is encrypted with an asymmetric encryption key and stored in the DB.
     * Finally, the encrypted symmetric key and the signature are stored on the DB.
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\FileAccessDenied
     * @throws \ParagonIE\Halite\Alerts\FileError
     * @throws \ParagonIE\Halite\Alerts\FileModified
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \ParagonIE\Halite\Alerts\InvalidMessage
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \SodiumException
     */
    public static function storeFile(Request $request)
    {
        $request->validate([
            'user_file' => 'max:5000', // Max 5000 Kb
        ]);
        $file = $request->file('user_file');
        if ($file == null) {
            return redirect('files')->with('message', "A file must be uploaded.");
        }
        if (!in_array($file->extension(), StorageController::$validExtensions)) {
            return redirect('files')->with('message', 'Not a valid file extension, please use one of:  ' . implode(', ', StorageController::$validExtensions));
        }        
        // 1. Generate the symmetric key
        // 2. Crypt the file and file name with it
        // 3. Store it after sealing with asymmetric encryption
        $enc_key = KeyFactory::generateEncryptionKey();
        $keyAsString = KeyFactory::export($enc_key)->getString();
        $cipheredName = Crypto::encrypt(new HiddenString($file->getClientOriginalName()), $enc_key);
        $cipheredExt = Crypto::encrypt(new HiddenString($file->extension()), $enc_key);
        $pubKey = StorageController::getUserPublicKey(Auth::user()->id);
        $sealed = AsymmetricCrypto::seal(new HiddenString($keyAsString), $pubKey);
        $privateSignKey = StorageController::getUserPrivateSignatureKey(Auth::user()->id);
        // Sign then encrypt: https://crypto.stackexchange.com/questions/5458/should-we-sign-then-encrypt-or-encrypt-then-sign
        $signature = File::sign($file, $privateSignKey);
        File::encrypt($file, storage_path() . '/app/' . $cipheredName, $enc_key);
        Repository::insertFile(Auth::user()->id, $cipheredName, $sealed, $cipheredExt, $signature);
        return redirect('files');
    }

    /** Stores a file in local storage, which overwrites an existing file. This means that the name of the file
     * must not be updated but the file's content as well as its signature are updated.
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\FileAccessDenied
     * @throws \ParagonIE\Halite\Alerts\FileError
     * @throws \ParagonIE\Halite\Alerts\FileModified
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \ParagonIE\Halite\Alerts\InvalidMessage
     * @throws \ParagonIE\Halite\Alerts\InvalidSignature
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \SodiumException
     */
    public static function editFile(Request $request)
    {
        $request->validate([
            'edit_file' => 'max:5000', // Max 5000 Kb
        ]);
        $newFile = $request->file('edit_file');
        if ($newFile == null) {
            return redirect('files')->with('message', "A file must be uploaded.");
        }
        if (!in_array($newFile->extension(), StorageController::$validExtensions)) {
            return redirect('files')->with('message', 'Not a valid file extension, please use one of:  ' . implode(', ', StorageController::$validExtensions));
        }
        $oldFile = Repository::getFile($request->id)->first();
        $oldName = $oldFile->name;
        $privateKey = StorageController::getUserPrivateKey($oldFile->owner_id);
        $keyAsString = AsymmetricCrypto::unseal($oldFile->enc_key, $privateKey);
        $enc_key = KeyFactory::importEncryptionKey($keyAsString);
        $file_ext = Crypto::decrypt($oldFile->file_ext, $enc_key)->getString();
        if (strcmp($newFile->extension(), $file_ext) !== 0) {
            return redirect('files')->with('message', "The file extensions are not matching.");
        }
        Storage::disk('local')->delete($oldName);
        $privateSignKey = StorageController::getUserPrivateSignatureKey(Auth::user()->id);
        $signature = File::sign($newFile, $privateSignKey);
        File::encrypt($newFile, storage_path() . '/app/' . $oldName, $enc_key);
        Repository::updateSignature($request->id, $signature);
        return redirect('files')->with('message', "The file was updated successfully.");
    }

    /** Downloads a file from local storage. There are two possible cases: the currently authenticated user is either
     * the owner of the file or not. If so, the download merely requires unsealing the symmetric encryption key
     * with the asymmetric encryption key. Otherwise, the public encryption key of the original owner must be fetched
     * to decrypt the symmetric encryption key and decrypt the file. The file is written to the output buffer
     * and sent to the user on the fly such that it is never stored in plain text on the server.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\FileAccessDenied
     * @throws \ParagonIE\Halite\Alerts\FileError
     * @throws \ParagonIE\Halite\Alerts\FileModified
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \ParagonIE\Halite\Alerts\InvalidMessage
     * @throws \ParagonIE\Halite\Alerts\InvalidSignature
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \SodiumException
     */
    public static function downloadFile(Request $request)
    {
        $privateKey = StorageController::getUserPrivateKey(Auth::user()->id);
        if ($request->has('owner') && $request->has('friend')) {
            // Symmetric key is encrypted when shared so info from the sender and the receiver are needed
            $sharedFile = Repository::getSharedFile($request->owner, $request->friend, $request->id)->first();
            $ownerPubKey = StorageController::getUserPublicKey($sharedFile->owner_id);
            $keyAsString = AsymmetricCrypto::decrypt($sharedFile->enc_key, $privateKey, $ownerPubKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            $file = Repository::getFile($request->id)->first();
            $decipheredName = Crypto::decrypt($file->name, $enc_key)->getString();
        } elseif(Repository::getFile($request->id)->first()->owner_id === Auth::user()->id) { // this check is required to avoid broken access control
            $file = Repository::getFile($request->id)->first();
            $keyAsString = AsymmetricCrypto::unseal($file->enc_key, $privateKey);
            $enc_key = KeyFactory::importEncryptionKey($keyAsString);
            $decipheredName = Crypto::decrypt($file->name, $enc_key)->getString();
        }
        else{
            abort(401);
        }
        $filepath = storage_path() . '/app/' . $file->name;
        // Write the file to the output buffer on the fly and empty it
        $stream = new MutableFile(fopen('php://output', 'wb'));
        ob_start();
        File::decrypt($filepath, $stream, $enc_key);
        $contents = ob_get_clean(); // buffer is empty after that
        // Can't believe it https://stackoverflow.com/questions/34624118/working-with-encrypted-files-in-laravel-how-to-download-decrypted-file
        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $decipheredName);
    }

    /** Checks that the signature of the uploaded file (the file to check), matches the signature of the file on
     * the server. Aside from providing non-repudiation, it also helps brings information about file change since
     * any edit will update the signature. For example, if Alice shares a file with Bob and signs it and then updates it,
     * and Bob downloaded in the meantime, before the edit, then checking the signature will let Bob know the
     * files are different, without downloading the updated file. Note that a signature mismatch does always imply
     * there was tampering, in this above case, it was a matter of a file update. Nonetheless, if Bob obtains a file
     * from different means that should be Alice's, it's possible the signature with Alice's public key.
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\FileAccessDenied
     * @throws \ParagonIE\Halite\Alerts\FileError
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \ParagonIE\Halite\Alerts\InvalidMessage
     * @throws \ParagonIE\Halite\Alerts\InvalidSignature
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \SodiumException
     */
    public static function checkSignature(Request $request)
    {
        $request->validate([
            'sign_file' => 'max:5000', // Max 5000 Kb
        ]);
        $file = $request->file('sign_file');
        if ($file == null) {
            return redirect('files')->with('message', "A file must be uploaded.");
        }
        if (!in_array($file->extension(), StorageController::$validExtensions)) {
            return redirect('files')->with('message', 'Not a valid file extension, please use one of:  ' . implode(', ', StorageController::$validExtensions));
        }
        $signature = Repository::getFileSignature($request->id);
        if ($request->has('owner') && $request->has('friend')) {
            $ownerSignPubKey = StorageController::getUserPublicSignatureKey($request->owner);
        } else {
            $ownerSignPubKey = StorageController::getUserPublicSignatureKey(Auth::user()->id);
        }
        $verify = File::verify($file, $ownerSignPubKey, $signature);
        if ($verify) {
            return redirect('files')->with('message', "The file was successfully verified!");
        } else {
            return redirect('files')->with('error', "The file couldn't be verified =(");
        }
    }

    /** Removes a file from local storage as well as references to it in the DB.
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public static function removeFile(Request $request)
    {
        $file = Repository::getFile($request->id)->first();
        Repository::removeFile($file->id);
        Storage::disk('local')->delete($file->name);
        return redirect('files');
    }

    /** Lets a file be shared from one user to another, if they are contacts. First, different checks establish
     * if there are actually confirmed contacts. Once this is done, the symmetric encryption key that encrypted
     * the files and file name is sent, encrypted under an asymmetric encryption scheme. The sender must first
     * unseal the symmetric encryption key and then encrypt it with the public encryption key of the receiver.
     * The encrypted symmetric key for this specific (owner, contact,file) triplet is then added to the DB.
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \ParagonIE\Halite\Alerts\CannotPerformOperation
     * @throws \ParagonIE\Halite\Alerts\InvalidDigestLength
     * @throws \ParagonIE\Halite\Alerts\InvalidKey
     * @throws \ParagonIE\Halite\Alerts\InvalidMessage
     * @throws \ParagonIE\Halite\Alerts\InvalidType
     * @throws \SodiumException
     */
    public static function shareFile(Request $request)
    {

        $owner_id = Auth::user()->id;
        $friend_id = Repository::getUserIdFromEmail($request->email);
        if ($owner_id == $friend_id) {
            return redirect('files')->with('message', "Can't share files with yourself.");
        }
        if ($friend_id == null) {
            return redirect('files')->with('message', 'No such friend');
        }
        // friendships are annoyingly one way so both ways have to be checked, since it's sufficient to be friend one-way to share
        // string are equal when strcmp is 0 in PHP
        $owner_friend_friendship = (Repository::getFriendship($owner_id, $friend_id)->first() == null) ? false
            : strcmp(Repository::getFriendship($owner_id, $friend_id)->first()->status, 'confirmed') === 0;
        $friend_owner_friendship = (Repository::getFriendship($friend_id, $owner_id)->first() == null) ? false
            : strcmp(Repository::getFriendship($friend_id, $owner_id)->first()->status, 'confirmed') === 0;
        $areFriends = $owner_friend_friendship || $friend_owner_friendship;
        $file = Repository::getFile($request->id)->first();
        if ($areFriends) {
            if (!Repository::sharedFileRecordExists($owner_id, $friend_id, $file->id)) {
                // Decrypting encryption key before sending it
                // And encrypting it with the pub key of the friend
                // Such that friend can decrypt with their own private key on their end
                $privateKey = StorageController::getUserPrivateKey(Auth::user()->id);
                $keyAsString = AsymmetricCrypto::unseal($file->enc_key, $privateKey);
                $friendPubKey = StorageController::getUserPublicKey($friend_id);
                $enc_for_friend = AsymmetricCrypto::encrypt($keyAsString, $privateKey, $friendPubKey);
                Repository::shareFileWithFriend($owner_id, $friend_id, $file->id, $enc_for_friend);
                return redirect('files')->with('message', 'File was shared successfully!');
            } else {
                return redirect('files')->with('message', 'File was already shared with this user.');
            }
        } else {
            return redirect('files')->with('message', 'No such friend');
        }
    }
}
