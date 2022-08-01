# Steps to create

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

Next, we need to install 

# Steps to use


