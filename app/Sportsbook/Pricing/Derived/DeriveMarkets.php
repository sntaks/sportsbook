<?php

namespace App\Sportsbook\Pricing\Derived;

use App\Enums\MarketSource;
use App\Enums\MarketType;
use App\Enums\SuspensionReason;
use App\Models\Fixture;
use App\Models\Market;
use App\Sportsbook\Pricing\ApplyMarketPrice;
use App\Sportsbook\Pricing\PricedMarket;

/**
 * Creates or refreshes a fixture's derived markets from its priced 1X2. An Over/Under line the feed already prices is
 * left to the feed. When the 1X2 cannot be priced, the derived markets are suspended with it.
 */
class DeriveMarkets
{
    public function __construct(
        private readonly DerivedMarketPricer $pricer,
        private readonly ApplyMarketPrice $applyMarketPrice,
        private readonly CompetitionGoals $competitionGoals,
    ) {}

    public function handle(Fixture $fixture): void
    {
        $matchResult = $fixture->markets()->where('type', MarketType::MatchResult)->with('selections')->first();
        $fair = $matchResult !== null ? $this->fairMatchResult($matchResult) : null;

        if ($matchResult === null || $fair === null || $matchResult->priced_at === null) {
            $this->suspendExisting($fixture);

            return;
        }

        $feedKeys = $fixture->markets()->where('source', MarketSource::Feed)->pluck('key')->all();
        $derivedMarkets = $this->pricer->price(
            $fair,
            $fixture->home_team,
            $fixture->away_team,
            $matchResult->bookmaker_count,
            $this->fairOverTwoAndAHalf($fixture),
            $this->competitionGoals->forCompetition($fixture->competition),
        );

        foreach ($derivedMarkets as $derived) {
            if (in_array(Market::keyFor($derived->type, $derived->line), $feedKeys, true)) {
                continue;
            }

            $this->applyMarketPrice->handle(
                $fixture,
                $derived->type,
                $derived->line,
                $derived->priced,
                $derived->selectionNames,
                createWhenUnpriced: true,
                source: MarketSource::Derived,
            );
        }
    }

    /**
     * @return array{home: float, draw: float, away: float}|null Null unless all three outcomes are priced.
     */
    private function fairMatchResult(Market $matchResult): ?array
    {
        $fair = $matchResult->selections->pluck('fair_probability', 'outcome');

        if (blank($fair['home'] ?? null) || blank($fair['draw'] ?? null) || blank($fair['away'] ?? null)) {
            return null;
        }

        return ['home' => (float) $fair['home'], 'draw' => (float) $fair['draw'], 'away' => (float) $fair['away']];
    }

    private function fairOverTwoAndAHalf(Fixture $fixture): ?float
    {
        $over = $fixture->markets()
            ->where('source', MarketSource::Feed)
            ->where('key', Market::keyFor(MarketType::Totals, 2.5))
            ->whereNotNull('priced_at')
            ->first()
            ?->selections()
            ->where('outcome', 'over')
            ->value('fair_probability');

        return is_numeric($over) ? (float) $over : null;
    }

    private function suspendExisting(Fixture $fixture): void
    {
        $fixture->markets()
            ->where('source', MarketSource::Derived)
            ->get()
            ->each(fn (Market $market) => $this->applyMarketPrice->handle(
                $fixture,
                $market->type,
                $market->line === null ? null : (float) $market->line,
                new PricedMarket(0, [], SuspensionReason::InsufficientBookmakers),
                [],
                createWhenUnpriced: false,
                source: MarketSource::Derived,
            ));
    }
}
