<?php

namespace App\Betting\Jackpot;

use App\Enums\JackpotStatus;
use App\Models\JackpotFixture;
use Illuminate\Support\Facades\Date;

/**
 * Voids jackpot matches that moved more than `postponement_void_hours` (48) from the kick-off the jackpot was
 * published with, then settles the jackpot if that was the last match it was waiting for. Like a postponed bet leg,
 * the match stays void even if a result comes in later.
 */
class VoidPostponedJackpotMatches
{
    public function __construct(private SettleJackpot $settleJackpot) {}

    /**
     * @return int How many jackpot matches were voided.
     */
    public function handle(): int
    {
        $hours = (int) config('sportsbook.settlement.postponement_void_hours');
        $voided = 0;

        $matches = JackpotFixture::query()
            ->whereNull('jackpot_fixtures.void_reason')
            ->whereNull('jackpot_fixtures.outcome')
            ->where('jackpot_fixtures.is_void', false)
            ->whereHas('jackpot', fn ($query) => $query->where('status', JackpotStatus::Open))
            ->join('fixtures', 'fixtures.id', '=', 'jackpot_fixtures.fixture_id')
            ->whereColumn('fixtures.kicks_off_at', '!=', 'jackpot_fixtures.kicks_off_at')
            ->select('jackpot_fixtures.*', 'fixtures.kicks_off_at as fixture_kicks_off_at')
            ->with('jackpot')
            ->get();

        foreach ($matches as $match) {
            $kickOff = $match->getAttribute('fixture_kicks_off_at');

            if (! is_string($kickOff) || Date::parse($kickOff)->diffInHours($match->kicks_off_at, true) <= $hours) {
                continue;
            }

            $match->update([
                'is_void' => true,
                'void_reason' => __('Match moved more than :hours hours from its published kick-off.', ['hours' => $hours]),
            ]);
            $this->settleJackpot->handle($match->jackpot);
            $voided++;
        }

        return $voided;
    }
}
