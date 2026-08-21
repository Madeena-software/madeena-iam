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

Route::get('storage/{path}', function (string $path) {
    $allowedPrefixes = ['logos/'];

    $isAllowed = false;
    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($path, $prefix) && strlen($path) > strlen($prefix)) {
            $isAllowed = true;
            break;
        }
    }

    if (! $isAllowed) {
        abort(404);
    }

    if (str_contains($path, '\\') || str_contains($path, "\0")) {
        abort(404);
    }

    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            abort(404);
        }
    }

    $disk = Storage::disk('public');

    if (! $disk->exists($path)) {
        abort(404);
    }

    return $disk->response($path);
})->where('path', '.*');

if (app()->environment('local')) {
    Route::get('/mail-preview/onboarding', function () {
        $user = \App\Models\User::first() ?? new \App\Models\User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        return new \App\Mail\OnboardingMail($user, 'https://sso.madeena.my.id/password-reset/dummy-token');
    });

    Route::get('/mail-preview/registration', function () {
        $user = \App\Models\User::first() ?? new \App\Models\User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $client = \App\Models\OauthClient::first() ?? new \App\Models\OauthClient([
            'name' => 'Madeena Client App',
        ]);
        return new \App\Mail\NewUserRegistrationAdminMail($user, $client);
    });

    Route::get('/mail-preview/reset-password', function () {
        $user = \App\Models\User::first() ?? new \App\Models\User([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $notification = new \Illuminate\Auth\Notifications\ResetPassword('dummy-token-123');
        return $notification->toMail($user);
    });
}


