<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\DTOs\DailyVolumeDTO;
use App\Services\DashboardMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

final class DepositVolumeChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '300px';

    public int | string | array $columnSpan = 1;

    public function getHeading(): string|Htmlable|null
    {
        return 'Volumen de depósitos (30 días)';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = app(DashboardMetricsService::class)->getDailyDepositVolume(30);

        $labels = array_map(
            fn (DailyVolumeDTO $dto): string => date('d M', strtotime($dto->day)),
            $rows,
        );

        $data = array_map(fn (DailyVolumeDTO $dto): float => $dto->total, $rows);

        return [
            'datasets' => [
                [
                    'label'           => 'Depósitos ($)',
                    'data'            => $data,
                    'borderColor'     => '#10b981',
                    'backgroundColor' => '#10b98133',
                    'fill'            => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
