<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Pages\Reports as ReportsPage;
use App\Reports\Reports;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The headline figures for the chosen days.
 */
class PeriodSummary extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    /** Loaded with the page, so it always shows the dates now chosen. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return ReportsPage::canAccess();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $period = ReportsPage::period($this->pageFilters);
        $summary = app(Reports::class)->summary($period);

        return [
            Stat::make(__('Turnover'), Money::format($summary['turnover']))
                ->description(__('Bets :stakes · jackpots :entries', ['stakes' => Money::format($summary['stakes']), 'entries' => Money::format($summary['jackpot_entries'])])),
            Stat::make(__('GGR'), Money::format($summary['ggr']))
                ->description(__('Sportsbook :sportsbook · jackpots :jackpot', ['sportsbook' => Money::format($summary['sportsbook_ggr']), 'jackpot' => Money::format($summary['jackpot_ggr'])]))
                ->color($summary['ggr'] < 0 ? 'danger' : 'success'),
            Stat::make(__('NGR'), Money::format($summary['ngr']))
                ->description(__('After :costs in promotion costs', ['costs' => Money::format($summary['promotion_costs'])]))
                ->color($summary['ngr'] < 0 ? 'danger' : 'success'),
            Stat::make(__('Sportsbook hold'), $summary['hold'] === null ? '—' : number_format($summary['hold'], 1).'%')
                ->description(__('GGR as a share of bet stakes')),
            Stat::make(__('Deposits'), Money::format($summary['deposits'])),
            Stat::make(__('Withdrawals'), Money::format($summary['withdrawals'])),
            Stat::make(__('Active players'), number_format($summary['active_players']))
                ->description(__(':count new sign-ups', ['count' => number_format($summary['sign_ups'])])),
            Stat::make(__('Tax withheld'), Money::format($summary['taxes']))
                ->description(trans_choice(':count day (EAT)|:count days (EAT)', $period->dayCount(), ['count' => $period->dayCount()])),
        ];
    }
}
