<?php

namespace App\Wallet\Withdrawals;

use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Support\Money;
use App\Wallet\Mpesa\MpesaApiException;
use App\Wallet\Mpesa\MpesaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Sends one queued withdrawal to M-Pesa (B2C).
 *
 * The withdrawal is claimed by switching it from queued to processing in a single update, so if the job ever runs
 * twice only one run sends money. A refusal from Safaricom (a 4xx answer or a non-zero response code) means nothing
 * was paid, so the money is returned. Anything else (a timeout, a 5xx) might have been paid: the withdrawal stays
 * processing and is never refunded automatically; its result callback or finance settles it.
 */
class PayOutWithdrawal
{
    public function __construct(private MpesaService $mpesa, private SettleWithdrawal $settleWithdrawal) {}

    public function handle(Withdrawal $withdrawal): void
    {
        $claimed = Withdrawal::query()
            ->whereKey($withdrawal->id)
            ->where('status', WithdrawalStatus::Queued)
            ->update(['status' => WithdrawalStatus::Processing, 'sent_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $withdrawal->refresh();

        try {
            $response = $this->mpesa->b2c([
                'Amount' => Money::toShillings($withdrawal->amount),
                'PartyB' => $withdrawal->phone,
                'Remarks' => 'Withdrawal',
                'Occasion' => "W{$withdrawal->id}",
            ]);
        } catch (MpesaApiException $e) {
            $refused = $e->statusCode !== null && $e->statusCode >= 400 && $e->statusCode < 500;

            $refused
                ? $this->settleWithdrawal->returnFunds($withdrawal, WithdrawalStatus::Failed, ['result_code' => $e->errorCode, 'result_description' => mb_substr($e->getMessage(), 0, 255)])
                : $this->keepForChecking($withdrawal, $e->getMessage());

            return;
        } catch (ConnectionException $e) {
            $this->keepForChecking($withdrawal, $e->getMessage());

            return;
        } catch (InvalidArgumentException $e) {
            // Missing configuration: the request never left this server.
            Log::channel('mpesa')->error("Withdrawal {$withdrawal->id} not sent: {$e->getMessage()}");
            $this->settleWithdrawal->returnFunds($withdrawal, WithdrawalStatus::Failed, ['result_description' => __('Payouts are not available right now.')]);

            return;
        }

        if ((string) ($response['ResponseCode'] ?? '') !== '0') {
            $this->settleWithdrawal->returnFunds($withdrawal, WithdrawalStatus::Failed, [
                'result_code' => (string) ($response['ResponseCode'] ?? ''),
                'result_description' => mb_substr((string) ($response['ResponseDescription'] ?? ''), 0, 255),
            ]);

            return;
        }

        $withdrawal->update([
            'conversation_id' => is_string($response['ConversationID'] ?? null) ? $response['ConversationID'] : null,
            'originator_conversation_id' => is_string($response['OriginatorConversationID'] ?? null) ? $response['OriginatorConversationID'] : null,
        ]);
    }

    private function keepForChecking(Withdrawal $withdrawal, string $reason): void
    {
        Log::channel('mpesa')->warning("Withdrawal {$withdrawal->id} may or may not have been sent; left for its result or finance: {$reason}");

        $withdrawal->update(['result_description' => mb_substr($reason, 0, 255)]);
    }
}
