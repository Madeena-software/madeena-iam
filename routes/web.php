<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Oauth\AuthorizationController;
use App\Http\Middleware\CheckClientAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    abort(403, 'Unauthorized access.');
});

Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->name('passport.authorizations.authorize')
    ->middleware(['web', CheckClientAccess::class]);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);

    Route::get('/password-reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password-reset', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('storage/{path}', function ($path) {
    if (! Storage::disk('s3')->exists($path)) {
        abort(404);
    }

    return Storage::disk('s3')->response($path);
})->where('path', '.*');
