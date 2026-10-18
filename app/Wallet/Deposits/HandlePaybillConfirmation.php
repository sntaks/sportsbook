<?php

namespace App\Wallet\Deposits;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Support\Money;
use App\Wallet\Mpesa\MpesaRedactor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Records a completed Paybill payment (C2B confirmation) and credits the player whose number was used as the
 * account number. Each M-Pesa transaction ID is recorded once, so a repeated confirmation pays nothing extra.
 * A payment to an account number that matches no player is kept as unmatched for admin to assign.
 */
class HandlePaybillConfirmation
{
    public function __construct(private CompleteDeposit $completeDeposit) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $redacted = MpesaRedactor::redact($payload);

        Log::channel('mpesa')->info('Paybill confirmation', $redacted);

        $receipt = $payload['TransID'] ?? null;
        $amount = $payload['TransAmount'] ?? null;

        if (! is_string($receipt) || $receipt === '' || ! is_numeric($amount)) {
            Log::channel('mpesa')->warning('Paybill confirmation without a transaction ID or amount ignored.');

            return;
        }

        if (MpesaDeposit::query()->where('mpesa_receipt', $receipt)->exists()) {
            return;
        }

        $accountNumber = is_scalar($payload['BillRefNumber'] ?? null) ? (string) $payload['BillRefNumber'] : '';
        $player = PaybillAccount::player($accountNumber);

        try {
            $deposit = MpesaDeposit::create([
                'user_id' => $player?->id,
                'channel' => MpesaDepositChannel::Paybill,
                'status' => $player === null ? MpesaDepositStatus::Unmatched : MpesaDepositStatus::Pending,
                // Masked by Safaricom; kept as it arrives.
                'phone' => mb_substr(is_scalar($payload['MSISDN'] ?? null) ? (string) $payload['MSISDN'] : '', 0, 64),
                'amount' => Money::fromAmount($amount),
                'account_reference' => mb_substr($accountNumber, 0, 255),
                'mpesa_receipt' => $receipt,
                'callback_payload' => $redacted,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if ($player === null) {
            Log::channel('mpesa')->warning("Paybill payment {$receipt} matched no player; kept as unmatched.");

            return;
        }

        $this->completeDeposit->handle($deposit, $receipt);
    }
}
