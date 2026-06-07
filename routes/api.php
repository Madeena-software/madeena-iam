<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::prefix('v1')->group(function () {
    Route::middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
    ])->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register', [AuthController::class, 'register']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('/user', [UserController::class, 'show']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });
});
