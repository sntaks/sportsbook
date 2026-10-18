<?php

namespace App\Wallet\Withdrawals;

use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Support\Money;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only places a withdrawal reaches a final outcome: paid, or not paid with the held money returned.
 *
 * Both run under a lock on the withdrawal row and do nothing to a withdrawal that is already settled, so a
 * repeated M-Pesa result, a status-check answer and an admin acting at the same time can only settle it once.
 * The refund is posted under "withdrawal-refund:{id}", so it can never be returned twice either.
 */
class SettleWithdrawal
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array<string, mixed>  $attributes  Extra columns to store, such as the result payload.
     */
    public function markPaid(Withdrawal $withdrawal, ?string $receipt, array $attributes = []): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $receipt, $attributes): Withdrawal {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status->isSettled()) {
                if ($withdrawal->status !== WithdrawalStatus::Completed) {
                    // The money was returned, yet M-Pesa now says it paid: the player may have been paid twice.
                    Log::channel('mpesa')->critical("Withdrawal {$withdrawal->id} was paid by M-Pesa after its money was returned. Check it by hand.", ['receipt' => $receipt]);
                    $withdrawal->update(['result_payload' => $attributes['result_payload'] ?? $withdrawal->result_payload]);
                }

                return $withdrawal;
            }

            $withdrawal->update([
                ...$attributes,
                'status' => WithdrawalStatus::Completed,
                'mpesa_receipt' => $receipt ?? $withdrawal->mpesa_receipt,
                'completed_at' => now(),
            ]);

            return $withdrawal;
        });
    }

    /**
     * Return the held money and close the withdrawal as failed (M-Pesa refused it) or rejected (an admin did).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function returnFunds(Withdrawal $withdrawal, WithdrawalStatus $status, array $attributes = []): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $status, $attributes): Withdrawal {
            $withdrawal = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status->isSettled()) {
                return $withdrawal;
            }

            $refund = $this->ledger->post(
                $withdrawal->user,
                WalletTransactionType::WithdrawalRefund,
                $withdrawal->amount,
                "withdrawal-refund:{$withdrawal->id}",
                source: $withdrawal,
                description: __('Withdrawal of :amount returned', ['amount' => Money::format($withdrawal->amount)]),
            );

            $withdrawal->update([...$attributes, 'status' => $status, 'refund_transaction_id' => $refund->id]);

            return $withdrawal;
        });
    }
}
