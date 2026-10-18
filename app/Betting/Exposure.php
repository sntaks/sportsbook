<?php

namespace App\Betting;

use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\FixtureStatus;
use App\Models\BetLeg;
use App\Models\Fixture;
use Carbon\CarbonImmutable;

/**
 * What the book stands to pay on each match still waiting on a result, pick by pick.
 *
 * A pick's payout is the sum of the potential payouts of the open bets on it. For a multi that assumes every other
 * leg wins, so it is the worst case, not the expected cost. A slip holds at most one pick per match, so each bet is
 * counted once per match.
 */
class Exposure
{
    /**
     * Matches with the largest worst-case payout first.
     *
     * @return list<array{fixture_id: int, match: string, competition: string, kicks_off_at: CarbonImmutable, bets: int, stakes: int, worst_pick: string, worst_payout: int, picks: list<array{pick: string, bets: int, stakes: int, payout: int}>}>
     */
    public function byMatch(int $limit = 100): array
    {
        $rows = BetLeg::query()
            ->join('bets', 'bets.id', '=', 'bet_legs.bet_id')
            ->join('fixtures', 'fixtures.id', '=', 'bet_legs.fixture_id')
            ->leftJoin('fixture_results', 'fixture_results.fixture_id', '=', 'bet_legs.fixture_id')
            ->where('bets.status', BetStatus::Open)
            ->where('bet_legs.status', BetLegStatus::Open)
            ->where('fixtures.status', FixtureStatus::Scheduled)
            ->whereNull('fixture_results.id')
            ->groupBy('bet_legs.fixture_id', 'bet_legs.market_name', 'bet_legs.selection_name')
            ->selectRaw('bet_legs.fixture_id, bet_legs.market_name, bet_legs.selection_name, count(*) as bets, sum(bets.stake) as stakes, sum(bets.potential_payout) as payout')
            ->toBase()
            ->get();

        $picksByFixture = [];

        foreach ($rows as $row) {
            $picksByFixture[(int) $row->fixture_id][] = [
                'pick' => "{$row->market_name}: {$row->selection_name}",
                'bets' => (int) $row->bets,
                'stakes' => (int) $row->stakes,
                'payout' => (int) $row->payout,
            ];
        }

        $matches = [];

        foreach (Fixture::query()->with('competition')->findMany(array_keys($picksByFixture)) as $fixture) {
            $picks = $picksByFixture[$fixture->id];
            usort($picks, fn (array $a, array $b): int => $b['payout'] <=> $a['payout']);

            $matches[] = [
                'fixture_id' => $fixture->id,
                'match' => $fixture->name(),
                'competition' => $fixture->competition->name,
                'kicks_off_at' => $fixture->kicks_off_at,
                'bets' => array_sum(array_column($picks, 'bets')),
                'stakes' => array_sum(array_column($picks, 'stakes')),
                'worst_pick' => $picks[0]['pick'],
                'worst_payout' => $picks[0]['payout'],
                'picks' => $picks,
            ];
        }

        usort($matches, fn (array $a, array $b): int => $b['worst_payout'] <=> $a['worst_payout']);

        return array_slice($matches, 0, $limit);
    }
}
