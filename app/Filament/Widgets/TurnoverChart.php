<?php

namespace App\Filament\Widgets;

use App\Reports\Period;
use App\Reports\Reports;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Turnover and GGR for each of the last 30 EAT days, in shillings.
 */
class TurnoverChart extends ChartWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return KeyFigures::canView();
    }

    public function getHeading(): string
    {
        return __('Turnover and GGR, last 30 days (KES)');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = array_reverse(app(Reports::class)->revenueByDay(Period::lastDays(30)));
        $shillings = fn (int $cents): float => $cents / (int) config('sportsbook.currency.minor_units');

        return [
            'datasets' => [
                [
                    'label' => __('Turnover'),
                    'data' => array_map(fn (array $day): float => $shillings($day['stakes'] + $day['jackpot_entries']), $days),
                    'borderColor' => '#f5c542',
                    'backgroundColor' => '#f5c542',
                ],
                [
                    'label' => __('GGR'),
                    'data' => array_map(fn (array $day): float => $shillings($day['sportsbook_ggr'] + $day['jackpot_ggr']), $days),
                    'borderColor' => '#32c850',
                    'backgroundColor' => '#32c850',
                ],
            ],
            'labels' => array_map(fn (array $day): string => CarbonImmutable::parse($day['date'])->format('j M'), $days),
        ];
    }
}
