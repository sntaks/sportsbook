<?php

namespace App\Betting\Settlement;

use App\Enums\BetStatus;
use App\Enums\FreeBetStatus;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Models\FreeBet;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a settlement so the bet can settle again after a correction: whatever it paid (winnings net of tax, or a
 * void refund) is taken back, and the bet is open again under the next settlement version.
 *
 * The player may already have spent or withdrawn the money, so the reversal may take the balance below zero; a
 * negative balance then blocks withdrawals and bets until it is covered. Cancelled bets are never reopened.
 *
 * A void free bet paid nothing, but was given a replacement free bet: that is taken back if still unused. If the
 * player already bet with it, it stays theirs, and the activity log records it.
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

            if ($bet->isFreeBet() && $bet->status === BetStatus::Void) {
                $this->takeBackReplacement($bet);
            }

            $bet->update([
                'status' => BetStatus::Open,
                'payout' => null,
                'bonus' => null,
                'payout_transaction_id' => null,
                'settled_at' => null,
                'settlement_version' => $bet->settlement_version + 1,
            ]);

            return $bet;
        });
    }

    private function takeBackReplacement(Bet $bet): void
    {
        $replacement = FreeBet::query()
            ->where('replaces_bet_id', $bet->id)
            ->where('status', '!=', FreeBetStatus::Void)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($replacement?->status === FreeBetStatus::Available) {
            $replacement->update(['status' => FreeBetStatus::Void, 'void_reason' => __('Its bet was reopened by a corrected result.'), 'voided_at' => now()]);
        } elseif ($replacement !== null) {
            activity('settlement')
                ->performedOn($bet)
                ->event('replacement-free-bet-kept')
                ->withProperties(['reference' => $bet->reference, 'free_bet_id' => $replacement->id])
                ->log('Replacement free bet already used, so not taken back');
        }
    }
}
