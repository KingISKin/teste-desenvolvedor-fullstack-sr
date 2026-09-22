<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransactionImportController;
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

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');

    Route::post('imports', [TransactionImportController::class, 'store'])
        ->middleware('throttle:imports')
        ->name('imports.store');
    Route::get('imports/{import}', [TransactionImportController::class, 'show'])
        ->whereNumber('import')
        ->middleware('can:view,import')
        ->name('imports.show');
});
