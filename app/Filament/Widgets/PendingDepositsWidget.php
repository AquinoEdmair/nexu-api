<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\DepositInvoice;
use App\Notifications\DepositFollowUpNotification;
use App\Services\DashboardMetricsService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

final class PendingDepositsWidget extends Widget
{
    protected static ?int $sort = 9;

    protected static string $view = 'filament.widgets.pending-deposits-widget';

    protected static ?string $pollingInterval = '30s';

    public int | string | array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Collection $invoices */
        $invoices = app(DashboardMetricsService::class)->getPendingInvoices(10);

        return ['invoices' => $invoices];
    }

    public function sendFollowUp(string $invoiceId): void
    {
        $invoice = DepositInvoice::with('user')->find($invoiceId);

        if (! $invoice || ! $invoice->user) {
            Notification::make()
                ->title('No se encontró la factura o usuario')
                ->danger()
                ->send();

            return;
        }

        $invoice->user->notify(new DepositFollowUpNotification($invoice));

        Notification::make()
            ->title("Seguimiento enviado a {$invoice->user->email}")
            ->success()
            ->send();
    }
}
