<?php

namespace App\Betting\Settlement;

use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Bet;
use App\Promotions\GrantFreeBet;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An admin cancels an open bet (for example one placed in error): the stake goes back and the bet is closed as
 * cancelled. Settled bets are corrected through results or leg voids instead, never cancelled. A free bet gets a
 * replacement free bet, not cash.
 */
class CancelBet
{
    public function __construct(private Ledger $ledger, private GrantFreeBet $grantFreeBet) {}

    /**
     * @throws ValidationException
     */
    public function handle(Bet $bet, Admin $admin, string $reason): Bet
    {
        $bet = DB::transaction(function () use ($bet, $reason): Bet {
            $bet = Bet::query()->whereKey($bet->id)->lockForUpdate()->firstOrFail();

            if ($bet->status !== BetStatus::Open) {
                throw ValidationException::withMessages(['bet' => __('Only open bets can be cancelled.')]);
            }

            $refund = $bet->isFreeBet()
                ? null
                : $this->ledger->post($bet->user, WalletTransactionType::StakeRefund, $bet->stake, "bet-cancel:{$bet->id}", source: $bet, description: __('Bet :reference cancelled', ['reference' => $bet->reference]));

            if ($bet->isFreeBet()) {
                $this->grantFreeBet->replacing($bet);
            }

            $bet->legs()->where('status', BetLegStatus::Open)->update(['status' => BetLegStatus::Void, 'void_reason' => mb_substr($reason, 0, 255), 'settled_at' => now()]);
            $bet->update(['status' => BetStatus::Cancelled, 'payout' => $refund === null ? 0 : $bet->stake, 'payout_transaction_id' => $refund?->id, 'settled_at' => now()]);

            return $bet;
        });

        activity('settlement')
            ->causedBy($admin)
            ->performedOn($bet)
            ->event('bet-cancelled')
            ->withProperties(['reference' => $bet->reference, 'reason' => $reason])
            ->log('Bet cancelled');

        return $bet;
    }
}
