<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\DepositVolumeChart;
use App\Filament\Widgets\FinancialSummaryWidget;
use App\Filament\Widgets\PendingDepositsWidget;
use App\Filament\Widgets\TopReferrersWidget;
use App\Filament\Widgets\UserGrowthChart;
use App\Filament\Widgets\WithdrawalsPendingWidget;
use App\Filament\Widgets\WithdrawalVolumeChart;
use App\Filament\Widgets\YieldHistoryChart;
use Filament\Pages\Dashboard as BaseDashboard;

final class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $routePath = '/';

    public function getWidgets(): array
    {
        return [
            AdminStatsOverview::class,
            DepositVolumeChart::class,
            YieldHistoryChart::class,
            WithdrawalVolumeChart::class,
            UserGrowthChart::class,
            WithdrawalsPendingWidget::class,
            PendingDepositsWidget::class,
            TopReferrersWidget::class,
            FinancialSummaryWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return 2;
    }
}
