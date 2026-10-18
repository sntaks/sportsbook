<?php

namespace App\Wallet\Withdrawals;

use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Wallet\Mpesa\MpesaRedactor;
use App\Wallet\Mpesa\MpesaResult;
use Illuminate\Support\Facades\Log;

/**
 * Applies the result Safaricom posts to the B2C result URL: paid, or refused with the money returned.
 *
 * Matched by the conversation IDs Safaricom gave when the payout was sent. If that answer never reached us, the only
 * link is the receiving number and amount, so the result is applied only when exactly one unanswered processing
 * withdrawal matches both; anything else is logged for finance.
 */
class HandlePayoutResult
{
    public function __construct(private SettleWithdrawal $settleWithdrawal) {}

    /**
     * @param  array<mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $redacted = MpesaRedactor::redact($payload);

        Log::channel('mpesa')->info('B2C result', $redacted);

        $result = MpesaResult::fromPayload($payload);

        if ($result === null) {
            Log::channel('mpesa')->warning('B2C result without a Result body ignored.');

            return;
        }

        $withdrawal = $this->find($result);

        if ($withdrawal === null) {
            Log::channel('mpesa')->warning('B2C result matched no withdrawal; check it by hand.', ['conversation_id' => $result->conversationId]);

            return;
        }

        $attributes = ['result_code' => $result->resultCode, 'result_description' => $result->resultDescription, 'result_payload' => $redacted];

        $result->succeeded()
            ? $this->settleWithdrawal->markPaid($withdrawal, $result->transactionId ?? $result->parameter('TransactionReceipt'), $attributes)
            : $this->settleWithdrawal->returnFunds($withdrawal, WithdrawalStatus::Failed, $attributes);
    }

    private function find(MpesaResult $result): ?Withdrawal
    {
        $ids = array_values(array_filter([$result->conversationId, $result->originatorConversationId]));

        if ($ids !== []) {
            $byConversation = Withdrawal::query()
                ->where(fn ($query) => $query->whereIn('conversation_id', $ids)->orWhereIn('originator_conversation_id', $ids))
                ->first();

            if ($byConversation !== null) {
                return $byConversation;
            }
        }

        // "254712345678 - Jane Doe"
        $phone = PhoneNumber::normalize(explode(' ', (string) $result->parameter('ReceiverPartyPublicName'))[0]);
        $amount = $result->parameter('TransactionAmount');

        if ($phone === null || ! is_numeric($amount)) {
            return null;
        }

        $candidates = Withdrawal::query()
            ->where('status', WithdrawalStatus::Processing)
            ->whereNull('conversation_id')
            ->where('phone', $phone)
            ->where('amount', Money::fromAmount($amount))
            ->limit(2)
            ->get();

        if ($candidates->count() !== 1) {
            return null;
        }

        $withdrawal = $candidates->first();
        $withdrawal?->update(['conversation_id' => $result->conversationId, 'originator_conversation_id' => $result->originatorConversationId]);

        return $withdrawal;
    }
}
