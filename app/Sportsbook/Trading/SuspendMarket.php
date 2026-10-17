<?php

namespace App\Sportsbook\Trading;

use App\Enums\SuspensionReason;
use App\Models\Admin;
use App\Models\Market;

/**
 * An admin closes a market to new bets. It stays closed through odds syncs until an admin reopens it.
 */
class SuspendMarket
{
    public function handle(Market $market, Admin $admin, string $reason): void
    {
        $market->suspend(SuspensionReason::Manual);

        activity('odds')
            ->causedBy($admin)
            ->performedOn($market)
            ->event('market-suspended')
            ->withProperties(['fixture' => $market->fixture->name(), 'market' => $market->name(), 'reason' => $reason])
            ->log('Market suspended');
    }
}
