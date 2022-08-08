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
use phpseclib3\Crypt\RSA;

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
     * @param  \Illuminate\Http\Request  $request
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
        $private = RSA::createKey();
        $public = $private->getPublicKey();
        $path = base_path() . '/privateKeys/' . $request->email . '/';
        if(!is_dir($path))
            mkdir($path, 0777, true);
        file_put_contents($path . 'key.pem', $private);
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'validated' => false, // default validated value
            'public_key_enc' => $public,
        ]);

        event(new Registered($user));

        //        Auth::login($user); Do NOT login automatically once the user is created
        Auth::logout();
        return redirect(RouteServiceProvider::HOME);
    }
}
