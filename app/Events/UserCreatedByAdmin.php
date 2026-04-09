<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserCreatedByAdmin
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly Admin  $admin,
        public readonly string $tempPassword,
    ) {}
}
