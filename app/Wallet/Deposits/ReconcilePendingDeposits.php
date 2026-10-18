<?php

namespace App\Wallet\Deposits;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Wallet\Mpesa\MpesaApiException;
use App\Wallet\Mpesa\MpesaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Finds out how STK deposits ended when their callback never arrived, so no player's money gets stuck.
 *
 * Each pending push older than `stk_query_after_seconds` is checked with STK Push Query at most once per that
 * interval. A push still pending after `stk_expire_after_minutes` is marked expired; if its success callback turns
 * up later anyway, HandleStkCallback still credits it.
 */
class ReconcilePendingDeposits
{
    /** Safaricom's answer while the player has not yet responded to the prompt. */
    private const STILL_PROCESSING = '500.001.1001';

    private const CANCELLED_BY_PLAYER = '1032';

    public function __construct(private MpesaService $mpesa, private CompleteDeposit $completeDeposit) {}

    /**
     * @return array{completed: int, closed: int, expired: int, waiting: int}
     */
    public function handle(int $limit = 50): array
    {
        $counts = ['completed' => 0, 'closed' => 0, 'expired' => 0, 'waiting' => 0];
        $queryAfter = now()->subSeconds((int) config('sportsbook.wallet.stk_query_after_seconds'));

        $deposits = MpesaDeposit::query()
            ->pending()
            ->where('channel', MpesaDepositChannel::Stk)
            ->whereNotNull('checkout_request_id')
            ->where('created_at', '<=', $queryAfter)
            ->where(fn ($query) => $query->whereNull('last_queried_at')->orWhere('last_queried_at', '<=', $queryAfter))
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($deposits as $deposit) {
            $counts[$this->reconcile($deposit)]++;
        }

        return $counts;
    }

    /**
     * @return 'completed'|'closed'|'expired'|'waiting'
     */
    private function reconcile(MpesaDeposit $deposit): string
    {
        if ($deposit->created_at !== null && $deposit->created_at->lte(now()->subMinutes((int) config('sportsbook.wallet.stk_expire_after_minutes')))) {
            return $this->closeIfPending($deposit, MpesaDepositStatus::Expired, []) ? 'expired' : 'waiting';
        }

        $deposit->update(['last_queried_at' => now(), 'query_attempts' => $deposit->query_attempts + 1]);

        try {
            $response = $this->mpesa->stkQuery((string) $deposit->checkout_request_id);
        } catch (MpesaApiException $e) {
            if ($e->errorCode !== self::STILL_PROCESSING) {
                Log::channel('mpesa')->warning("STK query for deposit {$deposit->id} failed: {$e->getMessage()}");
            }

            return 'waiting';
        } catch (ConnectionException|InvalidArgumentException $e) {
            Log::channel('mpesa')->warning("STK query for deposit {$deposit->id} failed: {$e->getMessage()}");

            return 'waiting';
        }

        $resultCode = (string) ($response['ResultCode'] ?? '');
        $attributes = ['result_code' => $resultCode, 'result_description' => mb_substr((string) ($response['ResultDesc'] ?? ''), 0, 255)];

        if ($resultCode === '') {
            return 'waiting';
        }

        if ($resultCode === '0') {
            // The query carries no receipt number; the callback fills it in if it ever arrives.
            $this->completeDeposit->handle($deposit, null, $attributes);

            return 'completed';
        }

        $status = $resultCode === self::CANCELLED_BY_PLAYER ? MpesaDepositStatus::Cancelled : MpesaDepositStatus::Failed;

        return $this->closeIfPending($deposit, $status, $attributes) ? 'closed' : 'waiting';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function closeIfPending(MpesaDeposit $deposit, MpesaDepositStatus $status, array $attributes): bool
    {
        return DB::transaction(function () use ($deposit, $status, $attributes): bool {
            $deposit = MpesaDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if (! $deposit->status->isPending()) {
                return false;
            }

            $deposit->update([...$attributes, 'status' => $status]);

            return true;
        });
    }
}
