<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

final class WithdrawalsPendingWidget extends Widget
{
    protected static ?int $sort = 6;

    protected static string $view = 'filament.widgets.withdrawals-pending-widget';

    protected static ?string $pollingInterval = '30s';

    public int | string | array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Collection $pending */
        $pending = app(DashboardMetricsService::class)->getPendingWithdrawals(5);

        return ['pending' => $pending];
    }
}
