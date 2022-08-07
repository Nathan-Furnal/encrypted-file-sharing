<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class StorageController extends Controller
{
    public static function getFiles(Request $request)
    {
        $allFiles = Repository::getFiles(Auth::user()->id);
        $sharedFiles = Repository::getSharedFiles(Auth::user()->id);
        $normal_files = [];
        $shared_files = [];
        foreach ($allFiles as $file) {
            array_push($normal_files, [
                'path' => $file->path,
                'name' => Crypt::decrypt($file->name),
            ]);
        }
        foreach ($sharedFiles as $file) {
            array_push($shared_files, [
                'path' => $file->path,
                'name' => Crypt::decrypt($file->name),
            ]);
        }
        $files = ['files' => $normal_files, 'sharedFiles' => $shared_files];
        return view('files.files', compact('files'));
    }

    public static function storeFile(Request $request)
    {
        $name = Crypt::encrypt($request->file('user_file')->getClientOriginalName());
        $path = $request->file('user_file')->storeAs(
            Auth::user()->id . '_files',
            $name.'.'.$request->file('user_file')->getClientOriginalExtension()
        );
        Repository::insertFile(Auth::user()->id, $path, $name);
        //dd('path :'. $path . ' name :' . $name);
        return redirect('files');
    }
    public static function downloadFile(Request $request)
    {
        return Storage::download($request->path, $request->name);
        return redirect('files');
    }

    public static function removeFile(Request $request){
        Storage::disk('local')->delete($request->path);
        Repository::removeFile(Auth::user()->id, $request->path);
        return redirect('files');
    }

    public static function shareFile(Request $request){
        $owner = Auth::user()->id;
        $friend = Repository::getUserIdFromEmail($request->email);
        $path = $request->path;
        $name = Crypt::encrypt($request->name);
        if($friend != null && Repository::getFriendship($owner, $friend) != null && Repository::getFile($owner, $path) != null){
            if(!Repository::sharedFileRecordExists($owner, $friend, $path)){
                Repository::shareFileWithFriend($owner, $friend, $path, $name);
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
