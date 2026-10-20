<?php

namespace App\Betting\Jackpot;

use App\Enums\JackpotEntryStatus;
use App\Enums\JackpotStatus;
use App\Enums\WalletTransactionType;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use App\Models\JackpotPick;
use App\Support\Money;
use App\Wallet\Ledger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Settles an open jackpot once every match has a result or is void (decisions 37 and 38):
 * - a void match counts as right for every entry;
 * - more void matches than the jackpot's void limit cancels it instead, refunding every entry;
 * - each prize tier's pool is split equally (to the cent, rounded down) between the entries with exactly that many
 *   right. A pool nobody wins is not paid out.
 *
 * Prizes are keyed "jackpot-prize:{entry}:{settlement version}", so an entry is paid once per settlement; a corrected
 * result reopens the jackpot under the next version (ReopenJackpot) after taking the earlier prizes back.
 */
class SettleJackpot
{
    public function __construct(private Ledger $ledger, private CancelJackpot $cancelJackpot) {}

    public function handle(Jackpot $jackpot): Jackpot
    {
        return DB::transaction(function () use ($jackpot): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if ($jackpot->status !== JackpotStatus::Open) {
                return $jackpot;
            }

            $matches = $jackpot->jackpotFixtures()->get();

            if ($matches->isEmpty() || $matches->contains(fn ($match): bool => ! $match->isDecided())) {
                return $jackpot;
            }

            $voids = $matches->where('is_void', true)->count();

            if ($voids > $jackpot->void_limit) {
                return $this->cancelJackpot->handle($jackpot, __(':count of :matches matches were void, more than the limit of :limit.', [
                    'count' => $voids,
                    'matches' => $matches->count(),
                    'limit' => $jackpot->void_limit,
                ]));
            }

            $correct = self::correctCounts($jackpot);
            $prizes = collect(self::tiers($jackpot, $correct))->pluck('each', 'correct');
            $winners = 0;

            foreach ($jackpot->entries()->where('status', JackpotEntryStatus::Open)->lockForUpdate()->get() as $entry) {
                $count = (int) $correct->get($entry->id, 0);
                $winners += $this->settleEntry($jackpot, $entry, $count, (int) $prizes->get($count, 0)) ? 1 : 0;
            }

            $jackpot->update(['status' => JackpotStatus::Settled, 'settled_at' => now()]);

            activity('settlement')
                ->performedOn($jackpot)
                ->event('jackpot-settled')
                ->withProperties(['jackpot' => $jackpot->name, 'winners' => $winners, 'void_matches' => $voids, 'version' => $jackpot->settlement_version])
                ->log('Jackpot settled');

            return $jackpot;
        });
    }

    /**
     * Each tier with how many entries have exactly that many right so far and what each would get, counting matches
     * still to be decided as wrong. Settlement uses this once every match is decided; before that it is a preview.
     *
     * @param  Collection<int, int>|null  $correct  Entry id => matches right, when already counted.
     * @return list<array{correct: int, pool: int, winners: int, each: int}>
     */
    public static function tiers(Jackpot $jackpot, ?Collection $correct = null): array
    {
        $correct ??= self::correctCounts($jackpot);

        return collect($jackpot->prize_tiers)
            ->map(function (array $tier) use ($correct): array {
                $winners = $correct->filter(fn (int $count): bool => $count === (int) $tier['correct'])->count();

                return [
                    'correct' => (int) $tier['correct'],
                    'pool' => (int) $tier['pool'],
                    'winners' => $winners,
                    'each' => $winners === 0 ? 0 : intdiv((int) $tier['pool'], $winners),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * How many matches each live (not refunded) entry has right, counted in the database.
     *
     * @return Collection<int, int> Entry id => matches right.
     */
    public static function correctCounts(Jackpot $jackpot): Collection
    {
        return JackpotPick::query()
            ->join('jackpot_fixtures', 'jackpot_fixtures.id', '=', 'jackpot_picks.jackpot_fixture_id')
            ->join('jackpot_entries', 'jackpot_entries.id', '=', 'jackpot_picks.jackpot_entry_id')
            ->where('jackpot_entries.jackpot_id', $jackpot->id)
            ->where('jackpot_entries.status', '!=', JackpotEntryStatus::Refunded)
            ->groupBy('jackpot_picks.jackpot_entry_id')
            ->selectRaw('jackpot_picks.jackpot_entry_id, sum(case when jackpot_fixtures.is_void = ? or jackpot_fixtures.outcome = jackpot_picks.pick then 1 else 0 end) as correct', [true])
            ->pluck('correct', 'jackpot_entry_id')
            ->map(fn (mixed $count): int => (int) $count);
    }

    /**
     * Pay the entry its prize (less winnings tax) or close it as lost. True when it won something.
     */
    private function settleEntry(Jackpot $jackpot, JackpotEntry $entry, int $correct, int $prize): bool
    {
        if ($prize === 0) {
            $entry->update(['status' => JackpotEntryStatus::Lost, 'correct_count' => $correct, 'prize' => 0, 'settled_at' => now()]);

            return false;
        }

        $version = $jackpot->settlement_version;
        $credit = $this->ledger->post($entry->user, WalletTransactionType::JackpotPrize, $prize, "jackpot-prize:{$entry->id}:{$version}", source: $entry, description: __(':jackpot: :count right', ['jackpot' => $jackpot->name, 'count' => $correct]));
        $tax = (int) round(max(0, $prize - $entry->stake) * (float) config('sportsbook.settlement.winnings_tax_rate'));

        if ($tax > 0) {
            $this->ledger->post($entry->user, WalletTransactionType::WinningsTax, -$tax, "jackpot-winnings-tax:{$entry->id}:{$version}", source: $entry, description: __('Tax on :amount winnings', ['amount' => Money::format($prize - $entry->stake)]));
        }

        $entry->update(['status' => JackpotEntryStatus::Won, 'correct_count' => $correct, 'prize' => $prize - $tax, 'prize_transaction_id' => $credit->id, 'settled_at' => now()]);

        return true;
    }
}
