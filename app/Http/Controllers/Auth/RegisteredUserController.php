<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use ParagonIE\Halite\KeyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        // Generate different keys for encryption and signing
        $enc_keyPair = KeyFactory::generateEncryptionKeyPair();
        $sign_keyPair = KeyFactory::generateSignatureKeyPair();
        $private_enc = $enc_keyPair->getSecretKey();
        $private_sign = $sign_keyPair->getSecretKey();
        $public_enc = $enc_keyPair->getPublicKey();
        $public_sign = $sign_keyPair->getPublicKey();
        $path = base_path() . '/privateKeys/' . $request->email . '/';
        if (!is_dir($path))
            mkdir($path, 0777, true);
        KeyFactory::save($private_enc, $path . 'key.pem');
        KeyFactory::save($private_sign, $path . 'sign.pem');
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'validated' => false, // default validated value
            'public_key_enc' => KeyFactory::export($public_enc)->getString(),
            'public_key_sign' => KeyFactory::export($public_sign)->getString(),
        ]);

        event(new Registered($user));

        //        Auth::login($user); Do NOT login automatically once the user is created
        Auth::logout();
        return redirect(RouteServiceProvider::HOME);
    }

    /** Destroys the current user and all related files.
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy()
    {
        $id = Auth::user()->id;
        $email = DB::table('users')->where('id', $id)->get()->first()->email;
        $files = DB::table('files')->where('owner_id', $id)->get('name')->toArray();
        $files = array_map(function ($item) {
            return $item->name;
        }, $files);
        Storage::disk('local')->delete($files);
        Storage::disk('keys')->deleteDirectory($email);
        DB::table('users')->where('id', $id)->delete();
        return redirect('register');
    }
}
