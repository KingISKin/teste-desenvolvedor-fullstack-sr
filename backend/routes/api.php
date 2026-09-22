<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
| Every route here is prefixed with /api and throttled by the "api" limiter.
| Only login is public; everything else requires a Sanctum Bearer token.
*/

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
});
