<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\YieldApplied;
use App\Models\User;
use App\Models\YieldLog;
use App\Notifications\AdminYieldFailedNotification;
use App\Services\YieldService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Throwable;

final class ApplyYieldToUsers implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $queue   = 'yields';
    public int    $tries   = 3;
    public int    $timeout = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 600];

    public function __construct(
        public readonly string  $yieldLogId,
        public readonly string  $scope,
        public readonly ?string $specificUserId = null,
    ) {}

    // ── ShouldBeUnique ───────────────────────────────────────────────────────

    public function uniqueId(): string
    {
        return $this->yieldLogId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    // ── Handle ───────────────────────────────────────────────────────────────

    public function handle(YieldService $yieldService): void
    {
        $yieldLog = YieldLog::findOrFail($this->yieldLogId);

        // Abort silently if already processed (protects against queue retries)
        if ($yieldLog->status !== 'processing') {
            return;
        }

        try {
            $this->buildUserQuery()->chunkById(
                100,
                function (Collection $users) use ($yieldLog, $yieldService): void {
                    $yieldService->applyBatch($yieldLog, $users->pluck('id')->all());
                }
            );

            $yieldService->markCompleted($yieldLog);

            YieldApplied::dispatch($yieldLog->fresh());
        } catch (Throwable $e) {
            // Mark the log as failed immediately so the admin can see the error
            // before the Job's retry backoff kicks in.
            $yieldService->markFailed($yieldLog, $e->getMessage());

            // Re-throw so Laravel records the job as failed and triggers failed().
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $yieldLog = YieldLog::find($this->yieldLogId);

        if ($yieldLog === null) {
            return;
        }

        app(YieldService::class)->markFailed($yieldLog, $e->getMessage());

        $admin = $yieldLog->appliedBy;
        if ($admin !== null) {
            $admin->notify(new AdminYieldFailedNotification($yieldLog, $e->getMessage()));
        }
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /** @return Builder<User> */
    private function buildUserQuery(): Builder
    {
        if ($this->scope === 'specific_user' && $this->specificUserId !== null) {
            return User::active()->has('wallet')->where('id', $this->specificUserId)->select('id');
        }

        return User::active()->has('wallet')->select('id');
    }
}
