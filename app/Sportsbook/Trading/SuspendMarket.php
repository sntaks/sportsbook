<?php

namespace App\Sportsbook\Trading;

use App\Enums\MarketSource;
use App\Enums\MarketType;
use App\Enums\SuspensionReason;
use App\Models\Admin;
use App\Models\Market;

/**
 * An admin closes a market to new bets. It stays closed through odds syncs until an admin reopens it.
 */
class SuspendMarket
{
    /**
     * Suspending a 1X2 also suspends the markets derived from it, since their prices depend on it.
     */
    public function handle(Market $market, Admin $admin, string $reason): void
    {
        $market->suspend(SuspensionReason::Manual);

        if ($market->type === MarketType::MatchResult) {
            $market->fixture->markets()
                ->where('source', MarketSource::Derived)
                ->open()
                ->get()
                ->each(fn (Market $derived) => $derived->suspend(SuspensionReason::Manual));
        }

        activity('odds')
            ->causedBy($admin)
            ->performedOn($market)
            ->event('market-suspended')
            ->withProperties(['fixture' => $market->fixture->name(), 'market' => $market->name(), 'reason' => $reason])
            ->log('Market suspended');
    }
}
