<?php

namespace App\Sportsbook\Trading;

use App\Enums\MarketSource;
use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Enums\SuspensionReason;
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

        if ($this->isStale($market)) {
            throw MarketCannotBeReopened::staleOdds();
        }

        $market->reopen();

        if ($market->type === MarketType::MatchResult) {
            $this->reopenDerivedMarkets($market);
        }

        activity('odds')
            ->causedBy($admin)
            ->performedOn($market)
            ->event('market-reopened')
            ->withProperties(['fixture' => $market->fixture->name(), 'market' => $market->name()])
            ->log('Market reopened');
    }

    /**
     * Reopen the derived markets that were suspended by hand along with this 1X2, when they are freshly priced.
     */
    private function reopenDerivedMarkets(Market $matchResult): void
    {
        $matchResult->fixture->markets()
            ->where('source', MarketSource::Derived)
            ->where('status', MarketStatus::Suspended)
            ->where('suspension_reason', SuspensionReason::Manual)
            ->whereHas('selections')
            ->get()
            ->reject(fn (Market $derived): bool => $derived->priced_at === null || $this->isStale($derived))
            ->each(fn (Market $derived) => $derived->reopen());
    }

    private function isStale(Market $market): bool
    {
        return $market->priced_at === null
            || $market->priced_at->lt(now()->subHours((int) config('sportsbook.odds.stale_after_hours')));
    }
}
