<?php

use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
| Fortify's own route definitions, loaded inside the localized route group (see routes/web.php)
| so auth pages are served in the visitor's language. Fortify::ignoreRoutes() is set in FortifyServiceProvider.
*/
require base_path('vendor/laravel/fortify/routes/routes.php');

// Public registration: throttle + honeypot (Fortify ships no limiter for this route).
// Route names are only indexed after refreshNameLookups(); without it getByName() returns null and the
// protection would be silently skipped — so fail loudly if the route cannot be found.
Route::getRoutes()->refreshNameLookups();
$register = Route::getRoutes()->getByName('register.store')
    ?? throw new LogicException('Fortify route [register.store] not found: registration would be unprotected.');
$register->middleware(['throttle:5,1', ProtectAgainstSpam::class]);
