<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\DTOs\MetricsOverviewDTO;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AdminStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '60s';

    /** Alert threshold — turns the pending-withdrawals card red. */
    private const PENDING_DANGER_THRESHOLD = 10;

    protected function getStats(): array
    {
        /** @var MetricsOverviewDTO $overview */
        $overview = app(DashboardMetricsService::class)->getOverview();

        $withdrawalColor = $overview->pendingWithdrawals > self::PENDING_DANGER_THRESHOLD ? 'danger' : 'warning';

        return [
            Stat::make('Usuarios activos', number_format($overview->activeUsers))
                ->description('usuarios registrados y activos')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Depósitos hoy', '$' . number_format($overview->depositsToday, 2))
                ->description('depósitos confirmados hoy')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success'),

            Stat::make('Retiros pendientes', number_format($overview->pendingWithdrawals))
                ->description('solicitudes por revisar')
                ->icon('heroicon-o-clock')
                ->color($withdrawalColor)
                ->url(route('filament.admin.resources.withdrawal-requests.index')),

            Stat::make('Balance total sistema', '$' . number_format($overview->systemBalanceTotal, 2))
                ->description('suma de todos los wallets')
                ->icon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }
}
