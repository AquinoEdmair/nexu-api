<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DepositInvoice;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BackfillDepositTransactions extends Command
{
    protected $signature = 'deposits:backfill-transactions
                            {--dry-run : Show what would be created without writing}';

    protected $description = 'Create pending Transaction records for DepositInvoices that have none.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $invoices = DepositInvoice::whereNull('transaction_id')
            ->whereIn('status', ['awaiting_payment', 'expired'])
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices to backfill.');
            return self::SUCCESS;
        }

        $this->info("Found {$invoices->count()} invoice(s) to backfill." . ($dryRun ? ' [DRY RUN]' : ''));

        $created = 0;

        foreach ($invoices as $invoice) {
            $txStatus = $invoice->status === 'awaiting_payment' ? 'pending' : 'expired';
            $amount   = (string) ($invoice->amount_expected ?? '0');

            $this->line("  {$invoice->invoice_id} | {$invoice->status} | {$invoice->currency} | {$amount}");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($invoice, $txStatus, $amount, &$created): void {
                $tx = Transaction::create([
                    'user_id'    => $invoice->user_id,
                    'type'       => 'deposit',
                    'amount'     => $amount,
                    'fee_amount' => '0.00000000',
                    'net_amount' => '0.00000000',
                    'currency'   => $invoice->currency,
                    'status'     => $txStatus,
                    'metadata'   => ['invoice_id' => $invoice->invoice_id, 'backfilled' => true],
                ]);

                $invoice->update(['transaction_id' => $tx->id]);
                $created++;
            });
        }

        if (!$dryRun) {
            $this->info("Created {$created} transaction(s).");
        }

        return self::SUCCESS;
    }
}
