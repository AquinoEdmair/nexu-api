<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\EliteTierController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\YieldController;
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
    Route::post('/email/resend', [EmailVerificationController::class, 'resendByEmail']);
    Route::post('/validate-referral-code', [ReferralController::class, 'validateCode']);
});

// Email verification link from the email (signed URL, no auth)
Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// ── Elite tiers (public) ─────────────────────────────────────────────────
Route::get('/elite/tiers', [EliteTierController::class, 'index']);

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
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');
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

    // Yields
    Route::get('/yields', [YieldController::class, 'index']);

    // Referrals
    Route::prefix('referrals')->group(function (): void {
        Route::get('/summary',        [ReferralController::class, 'summary']);
        Route::get('/network',        [ReferralController::class, 'network']);
        Route::get('/earnings',       [ReferralController::class, 'earnings']);
        Route::get('/points-history', [ReferralController::class, 'pointsHistory']);
    });
});
