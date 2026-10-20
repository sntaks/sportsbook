<?php

namespace App\Filament\Widgets;

use App\Enums\AdminPermission;
use App\Reports\Period;
use App\Reports\Reports;
use App\Support\Money;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The business at a glance for the period chosen on the dashboard (today, the last 7 days or the last 30, in whole
 * EAT days up to now): each figure, its change against the period just before it, and its last 30 days as a trend line.
 */
class KeyFigures extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /** @var array<string, string> */
    public const PERIODS = [
        'today' => 'Today',
        '7' => 'Last 7 days',
        '30' => 'Last 30 days',
    ];

    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    /** The figures are worked out from the ledger on each load, so they are not refreshed in the background. */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can(AdminPermission::ViewReports->value) ?? false;
    }

    protected function getHeading(): string
    {
        return __('Key figures (EAT)');
    }

    protected function getDescription(): string
    {
        return __('Turnover is cash staked on bets and jackpots. GGR is before tax; NGR is GGR less promotion costs (multibet bonuses and free-bet winnings). Trend lines show the last 30 days.');
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $choice = array_key_exists($this->pageFilters['period'] ?? null, self::PERIODS) ? $this->pageFilters['period'] : 'today';
        $period = Period::lastDays($choice === 'today' ? 1 : (int) $choice);
        $reports = app(Reports::class);
        $current = $reports->summary($period);
        $previous = $reports->summary($period->previous());
        $trends = array_reverse($reports->dailyFigures(Period::lastDays(30)));
        $comparedWith = match ($choice) {
            'today' => __('vs the same time yesterday'),
            '7' => __('vs the 7 days before'),
            default => __('vs the 30 days before'),
        };

        $stat = function (string $figure, string $label, string $value, Heroicon $icon, bool $upIsGood = true) use ($current, $previous, $trends, $comparedWith): Stat {
            [$description, $direction] = $this->change($current[$figure], $previous[$figure], $comparedWith, isPercentage: $figure === 'hold');

            return Stat::make($label, $value)
                ->icon($icon)
                ->description($description)
                ->descriptionIcon(match ($direction) {
                    1 => Heroicon::ArrowTrendingUp,
                    -1 => Heroicon::ArrowTrendingDown,
                    default => Heroicon::Minus,
                })
                ->color(match (true) {
                    $direction === 0 => 'gray',
                    ! $upIsGood => 'primary',
                    $direction === 1 => 'success',
                    default => 'danger',
                })
                ->chart(array_map(fn (array $day): float => (float) ($day[$figure] ?? 0), $trends));
        };
        $hold = fn (?float $hold): string => $hold === null ? '—' : number_format($hold, 1).'%';

        return [
            $stat('turnover', __('Turnover'), Money::format($current['turnover']), Heroicon::OutlinedBanknotes),
            $stat('ggr', __('GGR'), Money::format($current['ggr']), Heroicon::OutlinedScale),
            $stat('ngr', __('NGR'), Money::format($current['ngr']), Heroicon::OutlinedChartBar),
            $stat('promotion_costs', __('Promotion costs'), Money::format($current['promotion_costs']), Heroicon::OutlinedGift, upIsGood: false),
            $stat('hold', __('Sportsbook hold'), $hold($current['hold']), Heroicon::OutlinedReceiptPercent),
            $stat('active_players', __('Active players'), number_format($current['active_players']), Heroicon::OutlinedUsers),
            $stat('deposits', __('Deposits'), Money::format($current['deposits']), Heroicon::OutlinedArrowDownTray, upIsGood: false),
            $stat('withdrawals', __('Withdrawals'), Money::format($current['withdrawals']), Heroicon::OutlinedArrowUpTray, upIsGood: false),
            $stat('sign_ups', __('New sign-ups'), number_format($current['sign_ups']), Heroicon::OutlinedUserPlus),
        ];
    }

    /**
     * How a figure moved against the earlier period: the words to show and the direction (1 up, -1 down, 0 none).
     * Hold, already a percentage, moves in percentage points.
     *
     * @return array{0: string, 1: int}
     */
    private function change(int|float|null $current, int|float|null $previous, string $comparedWith, bool $isPercentage = false): array
    {
        if ($current === null || $previous === null) {
            return [__('Nothing to compare it with'), 0];
        }

        $direction = $current <=> $previous;

        if ($direction === 0) {
            return [__('No change :compared', ['compared' => $comparedWith]), 0];
        }

        if ($isPercentage) {
            return [sprintf('%+.1f %s %s', $current - $previous, __('pts'), $comparedWith), $direction];
        }

        if ($previous == 0) {
            return [$direction === 1 ? __('Up from 0 :compared', ['compared' => $comparedWith]) : __('Down from 0 :compared', ['compared' => $comparedWith]), $direction];
        }

        return [sprintf('%+.1f%% %s', ($current - $previous) / abs($previous) * 100, $comparedWith), $direction];
    }
}
