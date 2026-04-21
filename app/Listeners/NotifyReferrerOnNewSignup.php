<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserRegisteredWithReferral;
use App\Notifications\ReferralRegisteredNotification;

final class NotifyReferrerOnNewSignup
{
    public function handle(UserRegisteredWithReferral $event): void
    {
        $event->referrer->notify(new ReferralRegisteredNotification($event->newUser));
    }
}
