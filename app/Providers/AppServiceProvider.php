<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CommissionConfigUpdated;
use App\Events\DepositConfirmed;
use App\Events\UserCreatedByAdmin;
use App\Events\UserStatusChanged;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalRejected;
use App\Events\YieldApplied;
use App\Listeners\LogCommissionConfigChange;
use App\Listeners\NotifyAdminOnYieldCompleted;
use App\Listeners\NotifyUserOnDeposit;
use App\Listeners\NotifyUserOnStatusChange;
use App\Listeners\NotifyUserWithdrawalApproved;
use App\Listeners\NotifyUserWithdrawalRejected;
use App\Listeners\NotifyUsersOnYieldApplied;
use App\Listeners\ProcessReferralOnDeposit;
use App\Listeners\SendWelcomeEmailToNewUser;
use App\Models\Admin;
use App\Models\CommissionConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\YieldLog;
use App\Policies\AdminPolicy;
use App\Policies\CommissionConfigPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use App\Policies\WithdrawalRequestPolicy;
use App\Policies\YieldPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (str_contains(config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        ResetPassword::createUrlUsing(function (User $user, string $token) {
            return config('app.frontend_url').'/reset-password?token='.$token.'&email='.$user->email;
        });

        Gate::policy(Admin::class, AdminPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(YieldLog::class, YieldPolicy::class);
        Gate::policy(WithdrawalRequest::class, WithdrawalRequestPolicy::class);
        Gate::policy(CommissionConfig::class, CommissionConfigPolicy::class);

        Event::listen(UserCreatedByAdmin::class, SendWelcomeEmailToNewUser::class);
        Event::listen(UserStatusChanged::class, NotifyUserOnStatusChange::class);
        Event::listen(YieldApplied::class, NotifyUsersOnYieldApplied::class);
        Event::listen(YieldApplied::class, NotifyAdminOnYieldCompleted::class);
        Event::listen(WithdrawalApproved::class, NotifyUserWithdrawalApproved::class);
        Event::listen(WithdrawalRejected::class, NotifyUserWithdrawalRejected::class);
        Event::listen(CommissionConfigUpdated::class, LogCommissionConfigChange::class);
        Event::listen(DepositConfirmed::class, NotifyUserOnDeposit::class);
        Event::listen(DepositConfirmed::class, ProcessReferralOnDeposit::class);
    }
}
