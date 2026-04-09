<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

final class YieldHistoryChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '300px';

    public int | string | array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Rendimientos aplicados (últimos 10)';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $history = app(DashboardMetricsService::class)->getYieldHistory(10);

        $labels = $history->map(
            fn ($row): string => $row->applied_at?->format('d M') ?? '—'
        )->all();

        $totals = $history->map(fn ($row): float => (float) $row->total_applied)->all();

        $colors = $history->map(
            fn ($row): string => (float) $row->total_applied >= 0 ? '#10b981' : '#ef4444'
        )->all();

        return [
            'datasets' => [
                [
                    'label'           => 'Total aplicado ($)',
                    'data'            => $totals,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
