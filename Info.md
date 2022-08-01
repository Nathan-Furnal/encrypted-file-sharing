# Steps to create

Those steps are used **once** to create the initial project. The subsequent
points show how the project was build.

To create this project, one must first go into a parent folder, and use: 

``` shell
composer create-project laravel/laravel <project-name>
```

Next, we need to add the relevant libraries to use, which are
[`breeze`](https://laravel.com/docs/9.x/starter-kits), a starter kit to takes
care of authentication and
[`homestead`](https://laravel.com/docs/9.x/homestead), a web server running into
a VM which allows SSL (and thus HTTPS).

``` shell
composer require laravel/breeze --dev
```
which takes care of installing `breeze` and installing as well as compiling the
assets requires: 

``` shell
php artisan breeze:install
 
npm install
npm run build
```

Next, we need to install Homestead, the explanation [is detailed in the
docs](https://laravel.com/docs/9.x/homestead#installation-and-setup) but it
requires Vagrant which in turns requires VirtualBox (or Parallels). Once you've
followed the instructions, you can:
```shell
git clone https://github.com/laravel/homestead.git ~/Homestead # some other folder
```
Then,
```shell
cd ~/Homestead # or wherever you cloned Homestead
git checkout release
```
Finally,
```shell
# macOS / Linux...
bash init.sh
 
# Windows...
init.bat
```
This project uses a project specific `Homestead.yaml` file rather than putting
the details in the `~/Homestead` directory. So we shall header over the 
[per project
configuration](https://laravel.com/docs/9.x/homestead#per-project-installation). 

This requires to install Homestead in the project's directory:

```shell
composer require laravel/homestead --dev
```
Then,

``` shell
# macOS / Linux...
php vendor/bin/homestead make
 
# Windows...
vendor\\bin\\homestead make
```
Finally, you can run `vagrant up` and access the project at
`http://homestead.test` **OR** the name specified in the `sites` options of the
`Homestead.yaml` file. So in this case, you should use `http://secg4.test`
instead. Attention, do not forget [host name
resolution](https://laravel.com/docs/9.x/homestead#hostname-resolution),
otherwise you won't be able to access the website.

## Re-routing to HTTPS

The basic `nginx` web server shipped with the Homestead virtual box, provides
routes for HTTP and HTTPS, if we want to send all HTTP requests to HTTPS, we can
use Laravel's `Middleware`.

In `app/Http/Middleware/HttpsProtocol.php`: (create if it doesn't exist)

``` php
<?php
namespace App\Http\Middleware;

use Closure;

class HttpsProtocol {

    public function handle($request, Closure $next){
	
	if (!$request->secure()) {
	return redirect()->secure($request->getRequestUri());
	}

	return $next($request); 
	}
}
?>
```
Then in `app/Http/Kernel.php`, in the `$middlewareGroups` variable, add:

``` php
\App\Http\Middleware\HttpsProtocol::class,
```
The whole file should look like:

``` php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HttpsProtocol::class // Middleware added here
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ];
}
```

For this to work you must also add the `crt` file in the `etc/ssl/...` from
Vagrant to the Google Chrome authorities otherwise the connection won't be
considered secured.

## Modifying authentication to be checked by admin
See [this
article](https://dev.to/kingsconsult/customize-laravel-auth-laravel-breeze-registration-and-login-1769)
for custom registration rules.
See [this SO
question](https://stackoverflow.com/questions/69940373/check-if-user-is-active-when-logging-in-with-laravel-8-and-breeze)
for modifying the authentication rule.

# Steps to use


