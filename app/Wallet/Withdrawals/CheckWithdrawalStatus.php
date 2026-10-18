<?php

namespace App\Wallet\Withdrawals;

use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\Withdrawal;
use App\Wallet\Mpesa\MpesaApiException;
use App\Wallet\Mpesa\MpesaRedactor;
use App\Wallet\Mpesa\MpesaResult;
use App\Wallet\Mpesa\MpesaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Asks M-Pesa (Transaction Status) how a payout without a definite result ended. The answer arrives on the status
 * result URL. A "completed" answer marks the withdrawal paid; any other answer is only recorded, because "not found"
 * can also mean "not yet", and refunding a payout that was in fact made would pay the player twice.
 *
 * UNVERIFIED: whether Transaction Status accepts OriginalConversationID when there is no receipt number.
 */
class CheckWithdrawalStatus
{
    public function __construct(private MpesaService $mpesa, private SettleWithdrawal $settleWithdrawal) {}

    /**
     * @throws ValidationException
     */
    public function request(Withdrawal $withdrawal, Admin $admin): void
    {
        if ($withdrawal->status !== WithdrawalStatus::Processing) {
            throw ValidationException::withMessages(['withdrawal' => __('Only payouts waiting for an answer from M-Pesa can be checked.')]);
        }

        try {
            $response = $this->mpesa->b2cTransactionStatus(array_filter([
                'TransactionID' => $withdrawal->mpesa_receipt,
                'OriginalConversationID' => $withdrawal->originator_conversation_id,
            ]));
        } catch (MpesaApiException|ConnectionException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['withdrawal' => __('M-Pesa could not check this payout: :message', ['message' => $e->getMessage()])]);
        }

        $withdrawal->update(['status_query_conversation_id' => is_string($response['ConversationID'] ?? null) ? $response['ConversationID'] : null]);

        activity('payments')
            ->causedBy($admin)
            ->performedOn($withdrawal)
            ->event('withdrawal-status-checked')
            ->log('Withdrawal status check sent to M-Pesa');
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function handleResult(array $payload): void
    {
        $redacted = MpesaRedactor::redact($payload);

        Log::channel('mpesa')->info('B2C status result', $redacted);

        $result = MpesaResult::fromPayload($payload);
        $withdrawal = $result?->conversationId === null
            ? null
            : Withdrawal::query()->where('status_query_conversation_id', $result->conversationId)->first();

        if ($result === null || $withdrawal === null) {
            Log::channel('mpesa')->warning('B2C status result matched no withdrawal.');

            return;
        }

        if ($result->succeeded() && strcasecmp((string) $result->parameter('TransactionStatus'), 'Completed') === 0) {
            $this->settleWithdrawal->markPaid($withdrawal, $result->parameter('ReceiptNo'), ['result_payload' => $redacted]);

            return;
        }

        $withdrawal->update([
            'result_description' => mb_substr(__('Status check: :answer', ['answer' => $result->parameter('TransactionStatus') ?? $result->resultDescription]), 0, 255),
            'result_payload' => $redacted,
        ]);
    }
}
