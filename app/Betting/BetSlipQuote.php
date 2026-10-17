<?php

namespace App\Betting;

use App\Models\Selection;

/**
 * What a bet slip is worth right now: each pick checked against current prices, the combined odds and the possible
 * win for a stake. Built on the server from the player's picks, which are never trusted for prices.
 *
 * @phpstan-type Line array{selection: Selection, seen_price: string, status: 'ok'|'price_changed'|'unavailable', message: string|null}
 */
final readonly class BetSlipQuote
{
    /**
     * @param  list<Line>  $lines
     * @param  list<string>  $stakeErrors
     */
    public function __construct(
        public array $lines,
        public ?float $stake,
        public array $stakeErrors,
        public float $totalOdds,
        public float $possibleWin,
        public bool $isPayoutCapped,
    ) {}

    /**
     * @param  array<int, mixed>  $picks  [{fixture_id, selection_id, price}] as sent by the browser.
     */
    public static function for(array $picks, string $stake, BettableSelections $bettableSelections): self
    {
        $seenPrices = self::seenPrices($picks);
        $selections = $bettableSelections->find(array_keys($seenPrices));
        $lines = [];
        $fixturesOnSlip = [];

        foreach ($seenPrices as $selectionId => $seenPrice) {
            $selection = $selections->get($selectionId);

            if ($selection === null || isset($fixturesOnSlip[$selection->market->fixture_id])) {
                continue;
            }

            $fixturesOnSlip[$selection->market->fixture_id] = true;
            $unavailable = $bettableSelections->unavailableReason($selection);

            $lines[] = [
                'selection' => $selection,
                'seen_price' => $seenPrice,
                'status' => match (true) {
                    $unavailable !== null => 'unavailable',
                    $selection->price() !== $seenPrice => 'price_changed',
                    default => 'ok',
                },
                'message' => $unavailable,
            ];
        }

        $totalOdds = self::totalOdds($lines);
        [$stakeAmount, $stakeErrors] = self::validateStake($stake, $lines);
        $uncappedWin = $stakeAmount !== null ? $stakeAmount * $totalOdds : 0.0;
        $maxPayout = (float) config('sportsbook.limits.max_payout');

        return new self(
            $lines,
            $stakeAmount,
            $stakeErrors,
            $totalOdds,
            floor(min($uncappedWin, $maxPayout) * 100) / 100,
            $uncappedWin > $maxPayout,
        );
    }

    public function hasPriceChanges(): bool
    {
        return collect($this->lines)->contains(fn (array $line): bool => $line['status'] === 'price_changed');
    }

    public function hasUnavailable(): bool
    {
        return collect($this->lines)->contains(fn (array $line): bool => $line['status'] === 'unavailable');
    }

    public function bettableCount(): int
    {
        return collect($this->lines)->reject(fn (array $line): bool => $line['status'] === 'unavailable')->count();
    }

    /**
     * Current prices of the selections whose price moved, for the player to accept.
     *
     * @return array<int, string>
     */
    public function changedPrices(): array
    {
        return collect($this->lines)
            ->filter(fn (array $line): bool => $line['status'] === 'price_changed')
            ->mapWithKeys(fn (array $line): array => [$line['selection']->id => (string) $line['selection']->price()])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $picks
     * @return array<int, string> Selection id => price the player saw, latest pick last. Malformed picks are dropped.
     */
    private static function seenPrices(array $picks): array
    {
        $seen = [];

        foreach (array_slice($picks, 0, (int) config('sportsbook.limits.max_selections')) as $pick) {
            if (! is_array($pick) || ! is_numeric($pick['selection_id'] ?? null) || ! is_numeric($pick['price'] ?? null)) {
                continue;
            }

            $seen[(int) $pick['selection_id']] = number_format((float) $pick['price'], 2, '.', '');
        }

        return $seen;
    }

    /**
     * @param  list<Line>  $lines
     */
    private static function totalOdds(array $lines): float
    {
        $odds = 1.0;
        $priced = false;

        foreach ($lines as $line) {
            if ($line['status'] !== 'unavailable') {
                $odds *= (float) $line['selection']->price();
                $priced = true;
            }
        }

        return $priced ? floor(round($odds * 100, 6)) / 100 : 0.0;
    }

    /**
     * @param  list<Line>  $lines
     * @return array{0: float|null, 1: list<string>}
     */
    private static function validateStake(string $stake, array $lines): array
    {
        $stake = trim($stake);

        if ($stake === '') {
            return [null, []];
        }

        if (! is_numeric($stake)) {
            return [null, [__('Enter your stake as a number.')]];
        }

        $amount = (float) $stake;
        $minimum = (int) config('sportsbook.limits.min_stake');
        $maximum = (int) config('sportsbook.limits.max_stake');
        $errors = [];

        if ($amount < $minimum) {
            $errors[] = __('The minimum stake is KES :amount.', ['amount' => number_format($minimum)]);
        }

        if ($amount > $maximum) {
            $errors[] = __('The maximum stake is KES :amount.', ['amount' => number_format($maximum)]);
        }

        return [$amount, $errors];
    }
}
