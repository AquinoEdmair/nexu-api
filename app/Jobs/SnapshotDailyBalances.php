<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BalanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SnapshotDailyBalances implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function handle(BalanceService $balanceService): void
    {
        $count = $balanceService->snapshotAllBalances();

        Log::info("SnapshotDailyBalances: created {$count} snapshots.");
    }
}
