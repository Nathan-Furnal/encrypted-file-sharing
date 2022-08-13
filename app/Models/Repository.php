<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Repository.
 *
 * Provides a centralised system to query the project's database.
 *
 */
class Repository
{
    /**
     * @param int $user_id
     * @return \Illuminate\Support\Collection
     */
    public static function getAllUsers(int $user_id)
    {
        $users = DB::table('users')->where('id', '!=', $user_id)->get();
        return $users;
    }

    /**
     * @param int $user_id
     * @return \Illuminate\Support\Collection
     */
    public static function getFriends(int $user_id)
    {
        $sql_friends_sent = DB::table('friendships')
            ->join('users', 'users.id', '=', 'friendships.to_id')
            ->where('friendships.status', '=', 'confirmed')
            ->where('friendships.from_id', '=', $user_id);

        $friends = DB::table('friendships')
            ->join('users', 'users.id', '=', 'friendships.from_id')
            ->where('friendships.status', '=', 'confirmed')
            ->where('friendships.to_id', '=', $user_id)
            ->union($sql_friends_sent)
            ->get();

        return $friends;
    }

    /**
     * @param int $user_id
     * @return \Illuminate\Support\Collection
     */
    public static function getFriendsPending(int $user_id)
    {
        $friends = DB::table('friendships')
            ->join('users', 'users.id', '=', 'friendships.from_id')
            ->where('friendships.status', '=', 'pending')
            ->where('friendships.to_id', '=', $user_id)
            ->get();
        return $friends;
    }

    /**
     * @param int $from
     * @param int $to
     * @return void
     */
    public static function addFriend(int $from, int $to)
    {
        $friends = Repository::getFriends($from);
        $testFriendship = DB::table('friendships')->where('from_id', $from)->where('to_id', $to)->exists();
        if(!Repository::containFriend($friends, $to) && !$testFriendship){
            $sql = "INSERT INTO friendships (from_id, to_id, status, created_at)"
                . " VALUES (?,?,?,?)";
            DB::insert($sql, [$from, $to, 'pending', Carbon::now()]);
        }
    }


    /**
     * @param User $friends
     * @param User $candidateFriend
     * @return bool
     */
    public static function containFriend($friends,$candidateFriend){
        foreach($friends as $friend){
            if($friend->id == $candidateFriend){
                return true;
            }
        }
        return false;
    }

    /**
     * @param int $from
     * @param int $to
     * @return void
     */
    public static function confirmFriend(int $from, int $to)
    {
        DB::table('friendships')
            ->where('from_id', '=', $to)
            ->where('to_id', '=', $from)
            ->update(['status' => 'confirmed', 'created_at' => Carbon::now()]);
    }

    /**
     * @param int $from
     * @param int $to
     * @return void
     */
    public static function rejectFriend(int $from, int $to)
    {
        DB::table('friendships')
            ->where('from_id', '=', $to)
            ->where('to_id', '=', $from)
            ->delete();
    }

    /**
     * @param int $owner_id
     * @return \Illuminate\Support\Collection
     */
    public static function getFiles(int $owner_id)
    {
        $files = DB::table('files')->where('owner_id', '=', $owner_id)->get();
        return $files;
    }

    /**
     * @param int $file_id
     * @return \Illuminate\Support\Collection
     */
    public static function getFile(int $file_id)
    {
        return DB::table('files')
            ->where('id','=', $file_id)
            ->get();
    }

    /**
     * @param int $owner_id
     * @param string $name
     * @param string $enc_key
     * @param string $file_ext
     * @param string $signature
     * @return void
     */
    public static function insertFile(int $owner_id, string $name, string $enc_key, string $file_ext, string $signature)
    {
        DB::table('files')->insert(array(
            'owner_id' => $owner_id,
            'name' => $name,
            'enc_key' => $enc_key,
            'file_ext' => $file_ext,
            'signature' => $signature,
            'created_at' => Carbon::now(),
        ));
    }

    /**
     * @param int $file_id
     * @return void
     */
    public static function removeFile(int $file_id){
        DB::table('files')->where('id', '=', $file_id)->delete();
    }

    /**
     * @param int $owner_id
     * @param int $friend_id
     * @param int $file_id
     * @param string $enc_key
     * @return void
     */
    public static function shareFileWithFriend(int $owner_id, int $friend_id, int $file_id, string $enc_key){
        DB::insert('INSERT INTO file_sharing (owner_id, friend_id, file_id, enc_key) VALUE (?,?,?,?)', [$owner_id, $friend_id, $file_id, $enc_key]);
    }

    /**
     * @param int $owner_id
     * @param int $friend_id
     * @param int $file_id
     * @return bool
     */
    public static function sharedFileRecordExists(int $owner_id, int $friend_id, int $file_id){
        return DB::table('file_sharing')
            ->where('owner_id', '=', $owner_id)
            ->where('friend_id', '=', $friend_id)
            ->where('file_id', '=', $file_id)
            ->exists();
    }

    /**
     * @param int $person
     * @param int $friend
     * @return \Illuminate\Support\Collection
     */
    public static function getFriendship(int $person, int $friend){
        return DB::table('friendships')
            ->where('from_id', '=', $person)
            ->where('to_id', '=', $friend)
            ->get();
    }

    /**
     * @param string $email
     * @return mixed|null
     */
    public static function getUserIdFromEmail(string $email){
        $output = DB::table('users')
            ->where('email', '=', $email)->first();
        if($output == null){
            return null;
        }
        else{
            return $output->id;
        }
    }

    /**
     * @param int $user_id
     * @return \Illuminate\Support\Collection
     */
    public static function getSharedFiles(int $user_id){
        return DB::table('file_sharing')->where('friend_id', '=', $user_id)->get();
    }

    /**
     * @param int $owner_id
     * @param int $friend_id
     * @param int $file_id
     * @return \Illuminate\Support\Collection
     */
    public static function getSharedFile(int $owner_id, int $friend_id, int $file_id){
        return DB::table('file_sharing')
            ->where('owner_id', '=', $owner_id)
            ->where('friend_id', '=', $friend_id)
            ->where('file_id', '=', $file_id)
            ->get();
    }

    /**
     * @param int $user_id
     * @return mixed
     */
    public static function getEmailFromUserId(int $user_id){
        return DB::table('users')->where('id', '=', $user_id)->get()->first()->email;
    }

    /**
     * @param int $user_id
     * @return mixed
     */
    public static function getUserPublicKey(int $user_id){
        return DB::table('users')->where('id', '=', $user_id)->get('public_key_enc')->first()->public_key_enc;
    }

    /**
     * @param int $user_id
     * @return mixed
     */
    public static function getUserPublicSignKey(int $user_id){
        return DB::table('users')->where('id', '=', $user_id)->get('public_key_sign')->first()->public_key_sign;
    }

    /**
     * @param int $file_id
     * @param string $newSignature
     * @return void
     */
    public static function updateSignature(int $file_id, string $newSignature){
        DB::table('files')->where('id', $file_id)->update(['signature' => $newSignature]);
    }

    /**
     * @param int $file_id
     * @return mixed
     */
    public static function getFileSignature(int $file_id){
        return DB::table('files')->where('id','=', $file_id)->get()->first()->signature;
    }

    /**
     * @param int $user_id
     * @param int $friend_id
     */
    public static function removeFriendship(int $user_id, int $friend_id){
        DB::table('friendships')->where('from_id', $user_id)->where('to_id', $friend_id)->delete();
    }

    /**
     * @param int $user_id
     * @param int $friend_id
     */
    public static function removeSharedFiles(int $user_id, int $friend_id){
        DB::table('file_sharing')->where('owner_id', $user_id)->where('friend_id', $friend_id)->delete();
        DB::table('file_sharing')->where('owner_id', $friend_id)->where('friend_id', $user_id)->delete();        
    }
}

