<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DepositService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessDepositWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    /**
     * @param array{invoice_id: string, amount: string, currency: string, tx_hash: string} $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {}

    public function handle(DepositService $depositService): void
    {
        $depositService->processWebhook($this->payload);
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('ProcessDepositWebhook failed', [
            'payload' => $this->payload,
            'error'   => $e->getMessage(),
        ]);
    }
}
