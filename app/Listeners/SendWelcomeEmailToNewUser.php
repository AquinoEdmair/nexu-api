<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserCreatedByAdmin;
use App\Notifications\UserCreatedByAdminNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendWelcomeEmailToNewUser implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(UserCreatedByAdmin $event): void
    {
        $event->user->notify(
            new UserCreatedByAdminNotification($event->tempPassword)
        );
    }
}
