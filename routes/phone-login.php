<?php

use AdamDziuk\PhoneLogin\Http\Controllers\PhoneLoginController;
use AdamDziuk\PhoneLogin\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;

/*
 * No guest:{guard} or auth:{guard} here: both redirect wherever the app points
 * every guard, and next to staff accounts that is the staff area.
 */
Route::middleware(config('phone-login.routes.middleware'))
    ->prefix((string) config('phone-login.routes.prefix'))
    ->name((string) config('phone-login.routes.name'))
    ->group(function () {
        Route::middleware(RedirectIfAuthenticated::class)->group(function () {
            Route::get('login', [PhoneLoginController::class, 'create'])->name('login');

            Route::post('login', [PhoneLoginController::class, 'store'])
                ->middleware('throttle:phone-login-send')
                ->name('login.store');

            Route::get('code', [PhoneLoginController::class, 'code'])->name('code');

            Route::post('code', [PhoneLoginController::class, 'authenticate'])
                ->middleware('throttle:phone-login-verify')
                ->name('code.store');
        });

        Route::post('logout', [PhoneLoginController::class, 'destroy'])->name('logout');
    });
