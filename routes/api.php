<?php

use App\Http\Controllers\Api\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('/register', RegisterController::class);
        Route::post('/login', LoginController::class);
        Route::post('/forgot-password', ForgotPasswordController::class);
        Route::post('/reset-password', ResetPasswordController::class);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', MeController::class);
        Route::post('/logout', LogoutController::class);

        Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
            ->middleware('throttle:6,1');

        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware('signed')
            ->name('verification.verify');
    });
});




Route::prefix('client')
    ->middleware(['auth:sanctum', 'role:client'])
    ->group(function () {
        //
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        //
    });

Route::prefix('staff')
    ->middleware(['auth:sanctum', 'role:staff'])
    ->group(function () {
        //
    });