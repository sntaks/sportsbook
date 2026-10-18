<?php

namespace App\Betting\Settlement;

use App\Enums\BetLegStatus;
use App\Models\BetLeg;
use Illuminate\Support\Facades\Date;

/**
 * Voids open legs whose match moved more than `postponement_void_hours` (48) from the kick-off they were bet on.
 * Kick-off changes come from the free fixture sync, so this costs no credits.
 */
class VoidPostponedLegs
{
    public function __construct(private VoidLeg $voidLeg) {}

    /**
     * @return int How many legs were voided.
     */
    public function handle(): int
    {
        $hours = (int) config('sportsbook.settlement.postponement_void_hours');
        $voided = 0;

        $legs = BetLeg::query()
            ->where('bet_legs.status', BetLegStatus::Open)
            ->join('fixtures', 'fixtures.id', '=', 'bet_legs.fixture_id')
            ->whereColumn('fixtures.kicks_off_at', '!=', 'bet_legs.kicks_off_at')
            ->select('bet_legs.*', 'fixtures.kicks_off_at as fixture_kicks_off_at')
            ->get();

        foreach ($legs as $leg) {
            $kickOff = $leg->getAttribute('fixture_kicks_off_at');

            if (! is_string($kickOff) || Date::parse($kickOff)->diffInHours($leg->kicks_off_at, true) <= $hours) {
                continue;
            }

            $this->voidLeg->handle($leg, __('Match moved more than :hours hours from the kick-off this bet was placed on.', ['hours' => $hours]));
            $voided++;
        }

        return $voided;
    }
}
