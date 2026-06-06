<?php

use App\Http\Controllers\Oauth\AuthorizationController;
use App\Http\Middleware\CheckClientAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    abort(403, 'Unauthorized access.');
});

Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->name('passport.authorizations.authorize')
    ->middleware(['web', CheckClientAccess::class]);
