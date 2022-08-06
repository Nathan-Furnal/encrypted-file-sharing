<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\StorageController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth'])->name('dashboard');
Route::redirect('/', '/register');

Route::get('/home', [FriendsController::class, 'index'])
    ->middleware(['auth'])
    ->name('home');

Route::get('/users', [FriendsController::class, 'getUsers'])
    ->name('users.search');

Route::get('/friends', [FriendsController::class, 'getFriends'])
    ->middleware(['auth'])
    ->name('friends.list');

Route::get('/friends/add/{user}', [FriendsController::class, 'addFriend'])
    ->middleware(['auth'])
    ->name('friends.add');

Route::get('/friends/confirm/{user}', [FriendsController::class, 'confirmFriend'])
    ->middleware(['auth'])
    ->name('friends.confirm');

Route::get('/friends/reject/{user}', [FriendsController::class, 'rejectFriend'])
    ->middleware(['auth'])
    ->name('friends.reject');


Route::get('/files', [StorageController::class, 'getFiles'])
    ->middleware(['auth'])
    ->name('files');

Route::post('/store/add', [StorageController::class, 'storeFile'])
    ->middleware(['auth'])
    ->name('store.add');

Route::post('/store/download', [StorageController::class, 'downloadFile'])
    ->middleware(['auth'])
    ->name('download');

Route::post('/store/delete', [StorageController::class, 'removeFile'])
    ->middleware(['auth'])
    ->name('store.delete');

require __DIR__ . '/auth.php';
