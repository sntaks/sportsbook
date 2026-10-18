<?php

namespace App\Wallet\Deposits;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Wallet\Mpesa\MpesaRedactor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies the result Safaricom posts to the STK callback URL.
 *
 * - Success with the amount and phone we asked for: credited (CompleteDeposit), even if the deposit had already
 *   been given up on as expired, because the money really left the player's M-Pesa.
 * - Success with a different amount or phone: marked mismatch and not credited, for admin to check.
 * - Success for a CheckoutRequestID we never recorded: kept as unmatched for admin, never dropped.
 * - Cancelled by the player (1032) or any other failure: marked as such, unless already settled.
 * - A callback repeated for a finished deposit changes nothing.
 */
class HandleStkCallback
{
    private const CANCELLED_BY_PLAYER = '1032';

    public function __construct(private CompleteDeposit $completeDeposit) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $callback = $payload['Body']['stkCallback'] ?? null;
        $redacted = MpesaRedactor::redact($payload);

        Log::channel('mpesa')->info('STK callback', $redacted);

        if (! is_array($callback) || ! is_string($callback['CheckoutRequestID'] ?? null)) {
            Log::channel('mpesa')->warning('STK callback without a CheckoutRequestID ignored.');

            return;
        }

        $resultCode = (string) ($callback['ResultCode'] ?? '');
        $resultDescription = mb_substr((string) ($callback['ResultDesc'] ?? ''), 0, 255);
        $paid = $this->paidDetails($callback);
        $deposit = MpesaDeposit::query()->where('checkout_request_id', $callback['CheckoutRequestID'])->first();

        if ($deposit === null) {
            $this->keepUnknownPayment($callback['CheckoutRequestID'], $resultCode, $resultDescription, $paid, $redacted);

            return;
        }

        $attributes = ['result_code' => $resultCode, 'result_description' => $resultDescription, 'callback_payload' => $redacted];

        if ($resultCode !== '0') {
            DB::transaction(function () use ($deposit, $resultCode, $attributes): void {
                $deposit = MpesaDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

                if (in_array($deposit->status, [MpesaDepositStatus::Pending, MpesaDepositStatus::Expired], true)) {
                    $deposit->update([
                        ...$attributes,
                        'status' => $resultCode === self::CANCELLED_BY_PLAYER ? MpesaDepositStatus::Cancelled : MpesaDepositStatus::Failed,
                    ]);
                }
            });

            return;
        }

        if ($paid['amount'] !== $deposit->amount || $paid['phone'] !== $deposit->phone) {
            $this->markMismatch($deposit, $paid['receipt'], [...$attributes, 'paid_amount' => $paid['amount']]);

            return;
        }

        $this->completeDeposit->handle($deposit, $paid['receipt'], $attributes);
    }

    /**
     * The amount (cents), receipt and phone from a successful callback's metadata.
     *
     * @param  array<mixed>  $callback
     * @return array{amount: int|null, receipt: string|null, phone: string|null}
     */
    private function paidDetails(array $callback): array
    {
        $metadata = $callback['CallbackMetadata']['Item'] ?? null;

        $items = collect(is_array($metadata) ? $metadata : [])
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['Name'] ?? null))
            ->mapWithKeys(fn (array $item): array => [$item['Name'] => $item['Value'] ?? null]);

        $amount = $items->get('Amount');
        $receipt = $items->get('MpesaReceiptNumber');

        return [
            'amount' => is_numeric($amount) ? Money::fromAmount($amount) : null,
            'receipt' => is_string($receipt) ? $receipt : null,
            'phone' => PhoneNumber::normalize(is_scalar($items->get('PhoneNumber')) ? (string) $items->get('PhoneNumber') : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function markMismatch(MpesaDeposit $deposit, ?string $receipt, array $attributes): void
    {
        DB::transaction(function () use ($deposit, $receipt, $attributes): void {
            $deposit = MpesaDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if ($deposit->status === MpesaDepositStatus::Completed) {
                return;
            }

            $deposit->update([...$attributes, 'status' => MpesaDepositStatus::Mismatch, 'mpesa_receipt' => $receipt]);
        });

        Log::channel('mpesa')->warning("STK deposit {$deposit->id} was paid with a different amount or phone than requested; not credited.");
    }

    /**
     * A success for a push we have no record of (for example one whose request timed out on our side).
     *
     * @param  array{amount: int|null, receipt: string|null, phone: string|null}  $paid
     * @param  array<mixed>  $redacted
     */
    private function keepUnknownPayment(string $checkoutRequestId, string $resultCode, string $resultDescription, array $paid, array $redacted): void
    {
        if ($resultCode !== '0') {
            return;
        }

        try {
            MpesaDeposit::create([
                'channel' => MpesaDepositChannel::Stk,
                'status' => MpesaDepositStatus::Unmatched,
                'phone' => $paid['phone'] ?? '',
                'amount' => $paid['amount'] ?? 0,
                'paid_amount' => $paid['amount'],
                'checkout_request_id' => $checkoutRequestId,
                'mpesa_receipt' => $paid['receipt'],
                'result_code' => $resultCode,
                'result_description' => $resultDescription,
                'callback_payload' => $redacted,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        Log::channel('mpesa')->warning('Paid STK callback for an unknown CheckoutRequestID kept as unmatched.', ['amount' => Money::format($paid['amount'] ?? 0)]);
    }
}
