<?php

namespace App\Sportsbook\Trading;

use App\Models\Admin;
use App\Models\Market;

/**
 * An admin reopens a suspended market, provided it could legitimately take bets right now.
 */
class ReopenMarket
{
    /**
     * @throws MarketCannotBeReopened
     */
    public function handle(Market $market, Admin $admin): void
    {
        if ($market->fixture->hasKickedOff()) {
            throw MarketCannotBeReopened::kickedOff();
        }

        if ($market->priced_at === null || ! $market->selections()->exists()) {
            throw MarketCannotBeReopened::unpriced();
        }

        if ($market->priced_at->lt(now()->subHours((int) config('sportsbook.odds.stale_after_hours')))) {
            throw MarketCannotBeReopened::staleOdds();
        }

        $market->reopen();

        activity('odds')
            ->causedBy($admin)
            ->performedOn($market)
            ->event('market-reopened')
            ->withProperties(['fixture' => $market->fixture->name(), 'market' => $market->name()])
            ->log('Market reopened');
    }
}
