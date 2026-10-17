<?php

namespace App\Sportsbook\Pricing;

use App\Enums\MarketSource;
use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Enums\SuspensionReason;
use App\Models\Fixture;
use App\Models\Market;

/**
 * Writes a priced market to a fixture: feed prices, fair probabilities, a history snapshot whenever the offered
 * price changes, and the resulting market status.
 *
 * Admin overrides are never touched, a manually suspended market stays suspended, and a fixture that has kicked
 * off is never reopened. A market suspended for too few bookmakers or stale odds reopens once it is priced again.
 */
class ApplyMarketPrice
{
    /**
     * @param  array<string, string>  $selectionNames  Outcome => name shown to players, e.g. ['draw' => 'Draw'].
     * @param  bool  $createWhenUnpriced  Whether to create the market (suspended) when it cannot be priced yet.
     */
    public function handle(
        Fixture $fixture,
        MarketType $type,
        ?float $line,
        PricedMarket $priced,
        array $selectionNames,
        bool $createWhenUnpriced,
        MarketSource $source = MarketSource::Feed,
    ): ?Market {
        $key = Market::keyFor($type, $line);
        $market = $fixture->markets()->where('key', $key)->first();

        if ($market === null && ! $priced->isPriced() && ! $createWhenUnpriced) {
            return null;
        }

        // A line owned by the other source (e.g. a derived Over 2.5 the feed now prices too thinly) is left alone
        // unless this source can actually price it.
        if ($market !== null && $market->source !== $source && ! $priced->isPriced()) {
            return $market;
        }

        $market ??= new Market([
            'fixture_id' => $fixture->id,
            'type' => $type,
            'line' => $line,
            'key' => $key,
            'source' => $source,
            'status' => MarketStatus::Suspended,
        ]);

        $market->bookmaker_count = $priced->bookmakerCount;

        if (! $priced->isPriced()) {
            if (! $market->exists || $market->isOpen()) {
                $market->status = MarketStatus::Suspended;
                $market->suspension_reason = $priced->suspensionReason;
            }

            $market->save();

            return $market;
        }

        $isNew = ! $market->exists;
        // Feed prices take over a line we had been deriving; DeriveMarkets never replaces a feed-owned line.
        $market->source = $source;
        $market->priced_at = now();
        $market->save();

        foreach ($priced->outcomes as $outcome => $price) {
            $this->writeSelection($market, $outcome, $selectionNames[$outcome] ?? $outcome, $price);
        }

        $this->updateStatus($market, $fixture, $isNew);

        return $market;
    }

    /**
     * @param  array{fair_probability: float, price: string}  $price
     */
    private function writeSelection(Market $market, string $outcome, string $name, array $price): void
    {
        $selection = $market->selections()->firstOrNew(['outcome' => $outcome]);
        $offeredBefore = $selection->exists ? $selection->price() : null;

        $selection->fill([
            'name' => $name,
            'fair_probability' => $price['fair_probability'],
            'feed_price' => $price['price'],
        ])->save();

        $selection->refresh()->recordOfferedPriceChange($offeredBefore);
    }

    private function updateStatus(Market $market, Fixture $fixture, bool $isNew): void
    {
        if ($fixture->hasKickedOff()) {
            if ($market->isOpen() || $market->suspension_reason !== SuspensionReason::KickedOff) {
                $market->suspend(SuspensionReason::KickedOff);
            }

            return;
        }

        if ($market->isOpen()) {
            return;
        }

        if ($isNew || $market->suspension_reason?->clearsOnReprice()) {
            $market->reopen();
        }
    }
}
