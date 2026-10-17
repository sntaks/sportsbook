<?php

namespace App\Filament\Widgets;

use App\Enums\AdminPermission;
use App\Models\OddsApiCall;
use App\Sportsbook\OddsApi\CreditBudget;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The Odds API credit position: what is left on the plan, this month's spend against our cap, and the driver.
 */
class OddsApiCreditsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Odds API credits';

    public static function canView(): bool
    {
        return Filament::auth()->user()?->canAny([AdminPermission::ManageOdds->value, AdminPermission::ManageCompetitions->value]) ?? false;
    }

    protected function getStats(): array
    {
        $budget = app(CreditBudget::class);
        $cap = (int) config('sportsbook.odds_api.monthly_credit_cap');
        $reserve = (int) config('sportsbook.odds_api.credit_reserve');
        $spent = $budget->spentThisMonth();
        $remaining = $budget->remaining();
        $lastPaidCall = OddsApiCall::query()->where('credits_spent', '>', 0)->latest('created_at')->first();
        $isLive = config('sportsbook.odds_api.driver') === 'live';

        return [
            Stat::make(__('Remaining on plan'), $remaining === null ? '—' : number_format($remaining))
                ->description(__(':reserve held in reserve', ['reserve' => $reserve]))
                ->color($remaining !== null && $remaining - $reserve < 50 ? 'danger' : 'success'),
            Stat::make(__('Spent this month'), number_format($spent).' / '.number_format($cap))
                ->description(__('Our monthly cap'))
                ->color($spent >= $cap * 0.8 ? 'danger' : 'gray'),
            Stat::make(__('Last paid call'), $lastPaidCall?->created_at->diffForHumans() ?? __('Never'))
                ->description($lastPaidCall === null ? '' : __(':credits credits for :sport', ['credits' => $lastPaidCall->credits_spent, 'sport' => $lastPaidCall->sport_key])),
            Stat::make(__('Driver'), $isLive ? __('Live') : __('Replay'))
                ->description($isLive ? __('Scheduled odds syncs spend credits') : __('Recordings only; no credits spent'))
                ->color($isLive ? 'warning' : 'gray'),
        ];
    }
}
