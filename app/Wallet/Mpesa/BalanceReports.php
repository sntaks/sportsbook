<?php

namespace App\Wallet\Mpesa;

use App\Enums\MpesaBalanceReportStatus;
use App\Models\Admin;
use App\Models\MpesaBalanceReport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Account balance checks for the Paybill and B2C shortcodes. Safaricom answers on the balance result URL, so a
 * request is stored first and the answer fills it in, matched by conversation ID.
 */
class BalanceReports
{
    public const ACCOUNTS = ['paybill', 'b2c'];

    public function __construct(private MpesaService $mpesa) {}

    /**
     * @throws ValidationException
     */
    public function request(string $account, Admin $admin): MpesaBalanceReport
    {
        try {
            $response = $account === 'b2c' ? $this->mpesa->b2cAccountBalance() : $this->mpesa->c2bAccountBalance();
        } catch (MpesaApiException|ConnectionException|InvalidArgumentException $e) {
            throw ValidationException::withMessages(['account' => __('M-Pesa could not check the balance: :message', ['message' => $e->getMessage()])]);
        }

        $report = MpesaBalanceReport::create([
            'account' => $account,
            'conversation_id' => is_string($response['ConversationID'] ?? null) ? $response['ConversationID'] : null,
            'originator_conversation_id' => is_string($response['OriginatorConversationID'] ?? null) ? $response['OriginatorConversationID'] : null,
            'status' => MpesaBalanceReportStatus::Requested,
            'requested_by_admin_id' => $admin->id,
        ]);

        activity('payments')->causedBy($admin)->performedOn($report)->event('balance-check-requested')->log('M-Pesa balance check requested');

        return $report;
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function handleResult(array $payload): void
    {
        Log::channel('mpesa')->info('Balance result', MpesaRedactor::redact($payload));

        $result = MpesaResult::fromPayload($payload);
        $report = $result?->conversationId === null
            ? null
            : MpesaBalanceReport::query()->where('conversation_id', $result->conversationId)->first();

        if ($result === null || $report === null) {
            Log::channel('mpesa')->warning('Balance result matched no balance check.');

            return;
        }

        $report->update([
            'status' => $result->succeeded() ? MpesaBalanceReportStatus::Received : MpesaBalanceReportStatus::Failed,
            'balances' => $result->succeeded() ? self::parseBalances((string) $result->parameter('AccountBalance')) : null,
            'result_description' => $result->resultDescription,
            'received_at' => now(),
        ]);
    }

    /**
     * "Working Account|KES|46713.00|46713.00|0.00|0.00&Float Account|KES|0.00|…" as name, currency and the first
     * amount. UNVERIFIED against a live answer: Safaricom documents the format but its later fields vary.
     *
     * @return list<array{name: string, currency: string, available: float}>
     */
    public static function parseBalances(string $accountBalance): array
    {
        $balances = [];

        foreach (array_filter(explode('&', $accountBalance)) as $account) {
            $fields = explode('|', $account);

            if (count($fields) >= 3 && is_numeric($fields[2])) {
                $balances[] = ['name' => trim($fields[0]), 'currency' => trim($fields[1]), 'available' => (float) $fields[2]];
            }
        }

        return $balances;
    }
}
