<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\DTOs\FinancialSummaryDTO;
use App\Services\DashboardMetricsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

final class FinancialSummaryWidget extends Widget
{
    protected static ?int $sort = 8;

    protected static string $view = 'filament.widgets.financial-summary-widget';

    public int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var FinancialSummaryDTO $summary */
        $summary = app(DashboardMetricsService::class)->getFinancialSummary();

        return ['summary' => $summary];
    }
}
