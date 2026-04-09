<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\UserCreatedByAdminNotification;

final class NotificationService
{
    public function sendTemporaryPassword(User $user, string $tempPassword): void
    {
        $user->notify(new UserCreatedByAdminNotification($tempPassword));
    }
}
