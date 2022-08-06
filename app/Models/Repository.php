<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Repository
{

    public static function getAllUsers(int $user_id)
    {
        $users = DB::table('users')->where('id', '!=', $user_id)->get();
        return $users;
    }
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

    public static function getFriendsPending(int $user_id)
    {
        $friends = DB::table('friendships')
            ->join('users', 'users.id', '=', 'friendships.from_id')
            ->where('friendships.status', '=', 'pending')
            ->where('friendships.to_id', '=', $user_id)
            ->get();
        return $friends;
    }

    public static function addFriend(int $from, int $to)
    {
        $friends = Repository::getFriends($from);
        if(!Repository::containFriend($friends, $to)){
            $sql = "INSERT INTO friendships (from_id, to_id, status, created_at)"
                . " VALUES (?,?,?,?)";
            DB::insert($sql, [$from, $to, 'pending', Carbon::now()]);
        }
    }
    public static function containFriend($friends,$candidateFriend){
        foreach($friends as $friend){
            if($friend->id == $candidateFriend){
                return true;
            }
        }
        return false;
    }
    public static function confirmFriend(int $from, int $to)
    {
        DB::table('friendships')
            ->where('from_id', '=', $to)
            ->where('to_id', '=', $from)
            ->update(['status' => 'confirmed', 'created_at' => Carbon::now()]);
    }

    public static function rejectFriend(int $from, int $to)
    {
        DB::table('friendships')
            ->where('from_id', '=', $to)
            ->where('to_id', '=', $from)
            ->delete();
    }

    public static function getFiles(int $owner_id)
    {
        $files = DB::table('files')->where('owner_id', '=', $owner_id)->get();
        return $files;
    }

    public static function insertFile(int $owner_id,$path,$name)
    {
       DB::insert('INSERT INTO files (owner_id, path,name, created_at) VALUES (?,?,?,?)', [$owner_id, $path,$name, Carbon::now()]);
    }

    public static function removeFile(int $owner_id, string $path){
        DB::table('files')->where('owner_id', '=', $owner_id)->where('path', '=', $path)->delete();
    }
}
