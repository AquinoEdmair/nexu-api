<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\YieldLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class YieldApplied
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly YieldLog $yieldLog,
    ) {}
}
