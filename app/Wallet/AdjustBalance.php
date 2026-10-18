<?php

namespace App\Wallet;

use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Finance corrects a player's balance by hand. It is a ledger entry like any other (never an edit), carries the
 * admin who made it and the reason, and is in the activity log.
 */
class AdjustBalance
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @param  int  $amount  Cents; positive adds money, negative takes it away.
     *
     * @throws ValidationException
     */
    public function handle(User $player, Admin $admin, int $amount, string $reason): WalletTransaction
    {
        if ($amount === 0) {
            throw ValidationException::withMessages(['amount' => __('Enter an amount other than zero.')]);
        }

        try {
            $entry = $this->ledger->post(
                $player,
                WalletTransactionType::Adjustment,
                $amount,
                'adjustment:'.Str::uuid(),
                description: mb_substr($reason, 0, 255),
                admin: $admin,
            );
        } catch (InsufficientFunds $e) {
            throw ValidationException::withMessages(['amount' => __('The player only has :balance.', ['balance' => Money::format($e->balance)])]);
        }

        activity('payments')
            ->causedBy($admin)
            ->performedOn($player)
            ->event('balance-adjusted')
            ->withProperties(['amount' => Money::format($amount), 'reason' => $reason, 'wallet_transaction_id' => $entry->id])
            ->log('Balance adjusted');

        return $entry;
    }
}
