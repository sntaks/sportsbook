<?php

namespace App\Betting\Settlement;

use App\Betting\Payout;
use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Models\BetLeg;
use App\Support\Money;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Settles one open bet from its legs, once:
 * - any leg lost: the bet is lost at once, even with other legs still to play;
 * - otherwise it waits until every leg is decided, then pays the stake times the odds of the winning legs (a void
 *   leg counts as 1.00), capped at the maximum payout;
 * - every leg void: the stake goes back and the bet is void.
 *
 * Payouts are keyed "bet-payout:{id}:{settlement version}", so a bet can only be paid once per settlement; a corrected
 * result reopens the bet under the next version (ReopenBet) after taking the earlier payout back.
 */
class SettleBet
{
    public function __construct(private Ledger $ledger) {}

    public function handle(Bet $bet): Bet
    {
        return DB::transaction(function () use ($bet): Bet {
            $bet = Bet::query()->whereKey($bet->id)->lockForUpdate()->firstOrFail();

            if ($bet->status !== BetStatus::Open) {
                return $bet;
            }

            $legs = $bet->legs()->get();

            if ($legs->contains(fn (BetLeg $leg): bool => $leg->status === BetLegStatus::Lost)) {
                $bet->update(['status' => BetStatus::Lost, 'payout' => 0, 'settled_at' => now()]);

                return $bet;
            }

            if ($legs->contains(fn (BetLeg $leg): bool => $leg->status === BetLegStatus::Open)) {
                return $bet;
            }

            $wonPrices = $legs->filter(fn (BetLeg $leg): bool => $leg->status === BetLegStatus::Won)->map(fn (BetLeg $leg): string => $leg->price);
            $key = "bet-payout:{$bet->id}:{$bet->settlement_version}";

            if ($wonPrices->isEmpty()) {
                $refund = $this->ledger->post($bet->user, WalletTransactionType::StakeRefund, $bet->stake, $key, source: $bet, description: __('Bet :reference void', ['reference' => $bet->reference]));
                $bet->update(['status' => BetStatus::Void, 'payout' => $bet->stake, 'payout_transaction_id' => $refund->id, 'settled_at' => now()]);

                return $bet;
            }

            $payout = Payout::for($bet->stake, Payout::combinedOdds($wonPrices));
            $winnings = $this->ledger->post($bet->user, WalletTransactionType::Winnings, $payout, $key, source: $bet, description: __('Bet :reference won', ['reference' => $bet->reference]));
            $tax = (int) round(max(0, $payout - $bet->stake) * (float) config('sportsbook.settlement.winnings_tax_rate'));

            if ($tax > 0) {
                $this->ledger->post($bet->user, WalletTransactionType::WinningsTax, -$tax, "bet-winnings-tax:{$bet->id}:{$bet->settlement_version}", source: $bet, description: __('Tax on :amount winnings', ['amount' => Money::format($payout - $bet->stake)]));
            }

            $bet->update(['status' => BetStatus::Won, 'payout' => $payout - $tax, 'payout_transaction_id' => $winnings->id, 'settled_at' => now()]);

            return $bet;
        });
    }
}
