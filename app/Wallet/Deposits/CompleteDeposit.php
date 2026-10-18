<?php

namespace App\Wallet\Deposits;

use App\Enums\MpesaDepositStatus;
use App\Enums\WalletTransactionType;
use App\Models\MpesaDeposit;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Credits a paid deposit to the player's wallet, exactly once.
 *
 * Every deposit is credited under the key "mpesa-deposit:{id}", whichever way the news arrives: the STK callback,
 * an STK Push Query, or a Paybill confirmation. So a query that confirms a payment followed by the late callback
 * for it credits once; the callback only fills in the receipt number the query could not give.
 */
class CompleteDeposit
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @param  array<string, mixed>  $attributes  Extra columns to store, such as the result code or callback payload.
     */
    public function handle(MpesaDeposit $deposit, ?string $receipt, array $attributes = []): MpesaDeposit
    {
        return DB::transaction(function () use ($deposit, $receipt, $attributes): MpesaDeposit {
            $deposit = MpesaDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->status === MpesaDepositStatus::Completed) {
                if ($deposit->mpesa_receipt === null && $receipt !== null) {
                    $deposit->update(['mpesa_receipt' => $receipt]);
                }

                return $deposit;
            }

            if ($deposit->user === null) {
                throw new LogicException("Deposit {$deposit->id} has no player to credit.");
            }

            $credit = $this->ledger->post(
                $deposit->user,
                WalletTransactionType::Deposit,
                $deposit->amount,
                "mpesa-deposit:{$deposit->id}",
                source: $deposit,
                description: $receipt === null ? __('M-Pesa deposit') : __('M-Pesa deposit :receipt', ['receipt' => $receipt]),
            );

            $tax = (int) round($deposit->amount * (float) config('sportsbook.wallet.deposit_tax_rate'));

            if ($tax > 0) {
                $this->ledger->post(
                    $deposit->user,
                    WalletTransactionType::DepositTax,
                    -$tax,
                    "mpesa-deposit-tax:{$deposit->id}",
                    source: $deposit,
                );
            }

            $deposit->update([
                ...$attributes,
                'status' => MpesaDepositStatus::Completed,
                'mpesa_receipt' => $receipt ?? $deposit->mpesa_receipt,
                'wallet_transaction_id' => $credit->id,
                'completed_at' => now(),
            ]);

            return $deposit;
        });
    }
}
