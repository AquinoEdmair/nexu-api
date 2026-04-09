<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

final class TopReferrersWidget extends Widget
{
    protected static ?int $sort = 7;

    protected static string $view = 'filament.widgets.top-referrers-widget';

    public int | string | array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Collection $referrers */
        $referrers = app(DashboardMetricsService::class)->getTopReferrers(5);

        return ['referrers' => $referrers];
    }
}
