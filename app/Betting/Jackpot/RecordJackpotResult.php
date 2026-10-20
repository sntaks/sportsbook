<?php

namespace App\Betting\Jackpot;

use App\Betting\Settlement\MarketRules;
use App\Enums\JackpotStatus;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\JackpotFixture;

/**
 * Carries a fixture's result into every jackpot that has the match, then settles those jackpots. A corrected result
 * reopens a settled jackpot first, taking back its prizes. Matches voided on their own (postponed) stay void.
 */
class RecordJackpotResult
{
    public function __construct(private ReopenJackpot $reopenJackpot, private SettleJackpot $settleJackpot) {}

    /**
     * @return int How many jackpot matches changed.
     */
    public function handle(Fixture $fixture, FixtureResult $result): int
    {
        $outcome = null;

        if (! $result->is_void && $result->home_score !== null && $result->away_score !== null) {
            $outcome = MarketRules::resultCode($result->home_score, $result->away_score);
        }

        $matches = JackpotFixture::query()
            ->where('fixture_id', $fixture->id)
            ->whereNull('void_reason')
            ->whereHas('jackpot', fn ($query) => $query->whereIn('status', [JackpotStatus::Open, JackpotStatus::Settled]))
            ->with('jackpot')
            ->get();

        $changed = 0;

        foreach ($matches as $match) {
            if ($match->outcome === $outcome && $match->is_void === $result->is_void) {
                continue;
            }

            $this->reopenJackpot->handle($match->jackpot);
            $match->update(['outcome' => $outcome, 'is_void' => $result->is_void]);
            $this->settleJackpot->handle($match->jackpot);
            $changed++;
        }

        return $changed;
    }
}
