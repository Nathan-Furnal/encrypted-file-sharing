<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Models\User;
use Illuminate\support\Facades\Auth;

class FriendsController extends Controller
{

    public function index()
    {
        $users = Repository::getFriends(Auth::user()->id);
        return view('friends/index', ['users' => $users]);
    }

    public function getUsers()
    {
        $users = Repository::getAllUsers(Auth::user()->id);
        return view('friends/search', ['users' => $users]);
    }
    public function getFriends()
    {
        $friends = Repository::getFriends(Auth::user()->id);
        $friendsPending = Repository::getFriendsPending(Auth::user()->id);
        return view('friends/friendlist', ['friends' => $friends, 'friendsPending' => $friendsPending]);
    }

    public function addFriend(User $user)
    {
        Repository::addFriend(Auth::user()->id, $user->id);
        return redirect('friends');
    }

    public function confirmFriend(User $user)
    {
        Repository::confirmFriend(Auth::user()->id, $user->id);
        return redirect('friends');
    }

    public function rejectFriend(User $user)
    {
        Repository::rejectFriend(Auth::user()->id, $user->id);
        return redirect('friends');
    }
}
