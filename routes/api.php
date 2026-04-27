<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BalanceController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\CryptoCurrencyController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\EliteTierController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\InAppNotificationController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\WithdrawalCurrencyController;
use App\Http\Controllers\Api\YieldController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\ContactMessageController;
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

Route::post('/contact', [ContactMessageController::class, 'store']);
Route::get('/team', [TeamMemberController::class, 'index']);

// Email verification link from the email (signed URL, no auth)
Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// ── Crypto currencies (public) ───────────────────────────────────────────
Route::get('/crypto/currencies', [CryptoCurrencyController::class, 'index']);

// ── Withdrawal currencies (public) ───────────────────────────────────────
Route::get('/withdrawals/currencies', [WithdrawalCurrencyController::class, 'index']);

// ── Elite tiers (public) ─────────────────────────────────────────────────
Route::get('/elite/tiers', [EliteTierController::class, 'index']);

// ── Metrics (public) ─────────────────────────────────────────────────────
Route::prefix('metrics')->group(function (): void {
    Route::get('/global', [MetricsController::class, 'global']);
    Route::get('/ranking', [MetricsController::class, 'ranking']);
    Route::get('/gold', [MetricsController::class, 'gold']);
    Route::get('/news', [MetricsController::class, 'news']);
    Route::get('/activity', [MetricsController::class, 'activity']);
});

// ── Deposit currencies (public) ──────────────────────────────────────────
Route::get('/deposits/currencies', [DepositController::class, 'currencies']);

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
    Route::get('/deposits/commission-rate', [DepositController::class, 'commissionRate']);
    Route::post('/deposits', [DepositController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::get('/deposits/{id}', [DepositController::class, 'show']);
    Route::post('/deposits/{id}/confirm', [DepositController::class, 'confirm'])->middleware('throttle:20,1');

    // Withdrawals
    Route::get('/withdrawals/commission-rate', [WithdrawalController::class, 'commissionRate']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::delete('/withdrawals/{id}', [WithdrawalController::class, 'destroy']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);

    // Yields
    Route::get('/yields', [YieldController::class, 'index']);

    // Export
    Route::get('/export', [ExportController::class, 'index'])->middleware('throttle:20,1');

    // Investments (available → in_operation)
    Route::post('/investments', [InvestmentController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::get('/investments', [InvestmentController::class, 'index']);

    // Support Tickets
    Route::prefix('support')->group(function (): void {
        Route::get('/tickets',                    [SupportTicketController::class, 'index']);
        Route::post('/tickets',                   [SupportTicketController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/tickets/{id}',               [SupportTicketController::class, 'show']);
        Route::post('/tickets/{id}/messages',     [SupportTicketController::class, 'reply'])->middleware('throttle:30,1');
    });

    // In-app notifications
    Route::prefix('notifications')->group(function (): void {
        Route::get('/',            [InAppNotificationController::class, 'index']);
        Route::get('/unread-count', [InAppNotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [InAppNotificationController::class, 'markRead']);
        Route::post('/read-all',  [InAppNotificationController::class, 'markAllRead']);
    });

    // Referrals
    Route::prefix('referrals')->group(function (): void {
        Route::get('/summary',        [ReferralController::class, 'summary']);
        Route::get('/network',        [ReferralController::class, 'network']);
        Route::get('/earnings',       [ReferralController::class, 'earnings']);
        Route::get('/points-history', [ReferralController::class, 'pointsHistory']);
    });
});
