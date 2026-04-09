<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Prefix: /api/v1
| Guard: api (Sanctum)
|
*/

// ── Public (no auth required) ────────────────────────────────────────────
Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ── Metrics (public) ─────────────────────────────────────────────────────
Route::prefix('metrics')->group(function (): void {
    Route::get('/global', [MetricsController::class, 'global']);
    Route::get('/ranking', [MetricsController::class, 'ranking']);
    Route::get('/gold', [MetricsController::class, 'gold']);
    Route::get('/news', [MetricsController::class, 'news']);
});

// ── Webhook (HMAC-verified, no user auth) ────────────────────────────────
Route::post('/webhook/deposit', [WebhookController::class, 'deposit'])
    ->middleware('webhook.verify');

// ── Authenticated ────────────────────────────────────────────────────────
Route::middleware(['auth:api', 'user.active'])->group(function (): void {
    // Auth
    Route::prefix('auth')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Balance
    Route::get('/balance', [BalanceController::class, 'index']);
    Route::get('/balance/history', [BalanceController::class, 'history']);

    // Deposits
    Route::post('/deposits/initiate', [DepositController::class, 'initiate'])
        ->middleware('throttle:10,1');
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::get('/deposits/pending', [DepositController::class, 'pending']);
    Route::get('/deposits/invoices', [DepositController::class, 'invoices']);

    // Withdrawals
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::delete('/withdrawals/{id}', [WithdrawalController::class, 'destroy']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
});
