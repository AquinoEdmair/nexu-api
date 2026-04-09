<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

final class UserGrowthChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '300px';

    public int | string | array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Registros de usuarios (30 días)';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = app(DashboardMetricsService::class)->getUserGrowth(30);

        $labels = array_map(
            fn (array $row): string => date('d M', strtotime($row['day'])),
            $rows,
        );

        $data = array_map(fn (array $row): int => $row['count'], $rows);

        return [
            'datasets' => [
                [
                    'label'           => 'Nuevos usuarios',
                    'data'            => $data,
                    'borderColor'     => '#6366f1',
                    'backgroundColor' => '#6366f133',
                    'fill'            => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
