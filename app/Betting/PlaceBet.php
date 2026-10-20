<?php

namespace App\Betting;

use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Enums\FreeBetStatus;
use App\Enums\OddsChangePolicy;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Models\FreeBet;
use App\Models\Selection;
use App\Models\User;
use App\Promotions\MultibetBonus;
use App\Support\Money;
use App\Wallet\InsufficientFunds;
use App\Wallet\Ledger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Places the player's slip as one bet: a single for one pick, a multi for more (decision 30).
 *
 * Everything is checked again on the server inside one database transaction, with the picked selections locked so
 * an odds sync cannot change a price halfway: every pick must still be open, each match may appear once, and any
 * price that moved since the player added it must be acceptable under their odds-change setting. The bet is placed
 * at the current prices, and the stake leaves the wallet in the same transaction.
 *
 * With a free bet, its whole amount is the stake and nothing leaves the wallet; the slip's total odds must reach the
 * free bet's minimum. A cash multi records the multibet bonus it qualifies for (decision 42); a free bet gets none.
 *
 * A slip key makes it safe to submit twice: the second submission returns the bet the first one placed.
 */
class PlaceBet
{
    /** No 0/O, 1/I/L: references are read out to support. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const REFERENCE_LENGTH = 8;

    public function __construct(private BettableSelections $bettableSelections, private Ledger $ledger) {}

    /**
     * @param  array<int, mixed>  $picks  [{fixture_id, selection_id, price}] as the slip holds them.
     * @param  mixed  $stake  Whole shillings; ignored with a free bet, whose amount is the stake.
     *
     * @throws BetNotPlaced
     */
    public function handle(User $user, array $picks, mixed $stake, ?OddsChangePolicy $policy = null, ?string $clientKey = null, ?int $freeBetId = null): Bet
    {
        if (! $user->canBet()) {
            throw new BetNotPlaced(BetNotPlaced::NOT_ALLOWED, $user->isSuspended()
                ? __('Your account is suspended. Please contact support.')
                : __('Betting is paused while your break from betting runs.'));
        }

        if ($clientKey !== null && ($placed = $user->bets()->where('client_key', $clientKey)->first()) !== null) {
            return $placed;
        }

        $policy ??= $user->odds_change_policy;
        $stakeCents = $freeBetId === null ? $this->stake($stake) : 0;

        try {
            return DB::transaction(fn (): Bet => $this->place($user, $picks, $stakeCents, $policy, $clientKey, $freeBetId));
        } catch (UniqueConstraintViolationException $e) {
            // The same slip submitted twice at once: the other request placed it.
            $placed = $clientKey === null ? null : $user->bets()->where('client_key', $clientKey)->first();

            return $placed ?? throw $e;
        }
    }

    /**
     * @param  array<int, mixed>  $picks
     *
     * @throws BetNotPlaced
     */
    private function place(User $user, array $picks, int $stake, OddsChangePolicy $policy, ?string $clientKey, ?int $freeBetId): Bet
    {
        $requested = collect($picks)
            ->filter(fn (mixed $pick): bool => is_array($pick) && is_numeric($pick['selection_id'] ?? null))
            ->map(fn (array $pick): int => (int) $pick['selection_id'])
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            throw new BetNotPlaced(BetNotPlaced::EMPTY_SLIP, __('Add at least one selection to your slip.'));
        }

        if ($requested->count() > (int) config('sportsbook.limits.max_selections')) {
            throw new BetNotPlaced(BetNotPlaced::EMPTY_SLIP, __('A bet can have at most :max selections.', ['max' => config('sportsbook.limits.max_selections')]));
        }

        $freeBet = $freeBetId === null ? null : $this->freeBet($user, $freeBetId);
        $stake = $freeBet->amount ?? $stake;

        // Locked until the bet is written, so a price cannot change between checking it and recording it.
        Selection::query()->whereKey($requested->all())->lockForUpdate()->get();

        $quote = BetSlipQuote::for($picks, (string) Money::toShillings($stake), $this->bettableSelections, $freeBet !== null);

        if (count($quote->lines) !== $requested->count()) {
            throw new BetNotPlaced(BetNotPlaced::DUPLICATE_MATCH, __('Each match can only appear once on a bet, and every selection must still exist.'));
        }

        foreach ($quote->lines as $line) {
            if ($line['status'] === 'unavailable') {
                throw new BetNotPlaced(BetNotPlaced::UNAVAILABLE, __('Remove the selections that are closed, then place your bet.'));
            }

            if ($line['status'] === 'price_changed' && ! $policy->accepts($line['seen_price'], (string) $line['selection']->price())) {
                throw new BetNotPlaced(BetNotPlaced::PRICE_CHANGED, __('Some odds have changed. Check the new odds, then place your bet.'));
            }
        }

        if ($quote->stakeErrors !== []) {
            throw new BetNotPlaced(BetNotPlaced::STAKE, $quote->stakeErrors[0]);
        }

        $prices = collect($quote->lines)->map(fn (array $line): string => (string) $line['selection']->price());
        $odds = Payout::combinedOdds($prices);

        if ($freeBet !== null && (int) round((float) $odds * 100) < (int) round((float) $freeBet->min_odds * 100)) {
            throw new BetNotPlaced(BetNotPlaced::FREE_BET_MIN_ODDS, __('This free bet needs total odds of at least :odds.', ['odds' => $freeBet->min_odds]));
        }

        $payout = Payout::for($stake, $odds);
        $bonusPercent = $freeBet === null ? MultibetBonus::percentFor($prices) : 0;

        $bet = Bet::create([
            'user_id' => $user->id,
            'free_bet_id' => $freeBet?->id,
            'reference' => $this->reference(),
            'type' => count($quote->lines) === 1 ? BetType::Single : BetType::Multi,
            'status' => BetStatus::Open,
            'stake' => $stake,
            'total_odds' => $odds,
            'potential_payout' => $freeBet !== null ? $payout - $stake : $payout + MultibetBonus::amount($stake, $payout, $bonusPercent),
            'bonus_percent' => $bonusPercent,
            'odds_change_policy' => $policy,
            'client_key' => $clientKey,
            'placed_at' => now(),
        ]);

        foreach ($quote->lines as $line) {
            $selection = $line['selection'];
            $market = $selection->market;

            $bet->legs()->create([
                'selection_id' => $selection->id,
                'market_id' => $market->id,
                'fixture_id' => $market->fixture_id,
                'fixture_name' => $market->fixture->name(),
                'kicks_off_at' => $market->fixture->kicks_off_at,
                'market_type' => $market->type,
                'market_line' => $market->line,
                'market_name' => $market->name(),
                'outcome' => $selection->outcome,
                'selection_name' => $selection->name,
                'price' => $selection->price(),
                'status' => BetLegStatus::Open,
            ]);
        }

        if ($freeBet !== null) {
            $freeBet->update(['status' => FreeBetStatus::Used, 'used_at' => now()]);

            return $bet;
        }

        try {
            $debit = $this->ledger->post($user, WalletTransactionType::Stake, -$stake, "bet-stake:{$bet->id}", source: $bet, description: __('Bet :reference', ['reference' => $bet->reference]));
        } catch (InsufficientFunds $e) {
            throw new BetNotPlaced(BetNotPlaced::INSUFFICIENT_FUNDS, __('Your balance is :balance. Deposit to place this bet.', ['balance' => Money::format($e->balance)]));
        }

        $bet->update(['stake_transaction_id' => $debit->id]);

        return $bet;
    }

    /**
     * The player's free bet, locked so it can only be used once.
     *
     * @throws BetNotPlaced
     */
    private function freeBet(User $user, int $freeBetId): FreeBet
    {
        $freeBet = FreeBet::query()->whereKey($freeBetId)->where('user_id', $user->id)->lockForUpdate()->first();

        if ($freeBet === null || ! $freeBet->isUsable()) {
            throw new BetNotPlaced(BetNotPlaced::FREE_BET_UNAVAILABLE, __('That free bet can no longer be used.'));
        }

        return $freeBet;
    }

    /**
     * The stake in cents. Whole shillings only, within the stake limits.
     *
     * @throws BetNotPlaced
     */
    private function stake(mixed $stake): int
    {
        if (! is_scalar($stake) || ! preg_match('/^\d+$/', trim((string) $stake))) {
            throw new BetNotPlaced(BetNotPlaced::STAKE, __('Enter your stake in whole shillings.'));
        }

        return Money::fromShillings((int) trim((string) $stake));
    }

    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = '';

            for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
                $reference .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            if (! Bet::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique bet reference.');
    }
}
