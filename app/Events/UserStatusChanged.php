<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly string $reason,
        public readonly Admin  $admin,
    ) {}
}
