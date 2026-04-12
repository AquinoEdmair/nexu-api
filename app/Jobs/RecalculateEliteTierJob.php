<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\EliteTierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class RecalculateEliteTierJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries  = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $userId,
    ) {}

    public function handle(EliteTierService $service): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $previousTierId = $user->elite_tier_id;

        $service->recalculateForUser($user);

        $user->refresh();

        if ($user->elite_tier_id !== $previousTierId) {
            Log::info('RecalculateEliteTierJob: tier changed', [
                'user_id'          => $this->userId,
                'previous_tier_id' => $previousTierId,
                'new_tier_id'      => $user->elite_tier_id,
            ]);
        }
    }
}
