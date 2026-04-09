<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Admin;
use App\Models\CommissionConfig;

final class CommissionConfigUpdated
{
    public function __construct(
        public readonly CommissionConfig  $config,
        public readonly Admin             $admin,
        /** @var 'created'|'activated'|'deactivated' */
        public readonly string            $action,
        public readonly ?CommissionConfig $previousConfig,
    ) {}
}
