<?php

use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
| Fortify's own route definitions, loaded inside the localized route group (see routes/web.php)
| so auth pages are served in the visitor's language. Fortify::ignoreRoutes() is set in FortifyServiceProvider.
*/
require base_path('vendor/laravel/fortify/routes/routes.php');

// Public registration: throttle + honeypot (Fortify ships no limiter for this route).
Route::getRoutes()->getByName('register.store')?->middleware(['throttle:5,1', ProtectAgainstSpam::class]);
