<?php

namespace App\Betting\Settlement;

use App\Enums\BetStatus;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a settlement so the bet can settle again after a correction: whatever it paid (winnings net of tax, or a
 * void refund) is taken back, and the bet is open again under the next settlement version.
 *
 * The player may already have spent or withdrawn the money, so the reversal may take the balance below zero; a
 * negative balance then blocks withdrawals and bets until it is covered. Cancelled bets are never reopened.
 */
class ReopenBet
{
    public function __construct(private Ledger $ledger) {}

    public function handle(Bet $bet): Bet
    {
        return DB::transaction(function () use ($bet): Bet {
            $bet = Bet::query()->whereKey($bet->id)->lockForUpdate()->firstOrFail();

            if (in_array($bet->status, [BetStatus::Open, BetStatus::Cancelled], true)) {
                return $bet;
            }

            if (($bet->payout ?? 0) > 0) {
                $this->ledger->post(
                    $bet->user,
                    WalletTransactionType::SettlementReversal,
                    -$bet->payout,
                    "bet-reversal:{$bet->id}:{$bet->settlement_version}",
                    source: $bet,
                    description: __('Bet :reference: result corrected', ['reference' => $bet->reference]),
                    allowNegative: true,
                );
            }

            $bet->update([
                'status' => BetStatus::Open,
                'payout' => null,
                'payout_transaction_id' => null,
                'settled_at' => null,
                'settlement_version' => $bet->settlement_version + 1,
            ]);

            return $bet;
        });
    }
}
