<?php

namespace App\Sportsbook\Sync;

use App\Enums\MarketStatus;
use App\Enums\SuspensionReason;
use App\Models\Fixture;
use App\Models\Market;

/**
 * Closes betting on open markets whose fixture has kicked off (there is no in-play betting) or whose odds have not
 * been refreshed within the stale window.
 */
class SuspendStaleMarkets
{
    /**
     * @return array{kicked_off: int, stale: int} The number of markets suspended for each reason.
     */
    public function handle(): array
    {
        $kickedOff = Market::query()
            ->open()
            ->whereIn('fixture_id', Fixture::query()->select('id')->where('kicks_off_at', '<=', now()))
            ->update(['status' => MarketStatus::Suspended, 'suspension_reason' => SuspensionReason::KickedOff, 'updated_at' => now()]);

        $stale = Market::query()
            ->open()
            ->where(fn ($query) => $query
                ->whereNull('priced_at')
                ->orWhere('priced_at', '<', now()->subHours((int) config('sportsbook.odds.stale_after_hours'))))
            ->update(['status' => MarketStatus::Suspended, 'suspension_reason' => SuspensionReason::StaleOdds, 'updated_at' => now()]);

        return ['kicked_off' => $kickedOff, 'stale' => $stale];
    }
}
