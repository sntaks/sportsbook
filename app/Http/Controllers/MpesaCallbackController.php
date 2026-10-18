<?php

namespace App\Http\Controllers;

use App\Wallet\Deposits\HandlePaybillConfirmation;
use App\Wallet\Deposits\HandleStkCallback;
use App\Wallet\Deposits\PaybillAccount;
use App\Wallet\Mpesa\BalanceReports;
use App\Wallet\Mpesa\MpesaRedactor;
use App\Wallet\Withdrawals\CheckWithdrawalStatus;
use App\Wallet\Withdrawals\HandlePayoutResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Safaricom's callbacks. The secret in the URL and the optional IP allowlist are checked by VerifyMpesaCallback.
 */
class MpesaCallbackController extends Controller
{
    public function stk(Request $request, HandleStkCallback $handleStkCallback): JsonResponse
    {
        $handleStkCallback->handle($request->all());

        return $this->accepted();
    }

    /**
     * Only called when Safaricom has enabled external validation on the shortcode. Accepts a payment whose account
     * number is a player who may deposit, and rejects the rest (no such player, suspended, or on a break from
     * betting), so the payer keeps their money.
     */
    public function paybillValidation(Request $request): JsonResponse
    {
        Log::channel('mpesa')->info('Paybill validation', MpesaRedactor::redact($request->all()));

        $player = PaybillAccount::player($request->input('BillRefNumber'));

        if ($player === null || ! $player->canDeposit()) {
            return response()->json(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
        }

        return $this->accepted();
    }

    public function paybillConfirmation(Request $request, HandlePaybillConfirmation $handlePaybillConfirmation): JsonResponse
    {
        $handlePaybillConfirmation->handle($request->all());

        return $this->accepted();
    }

    public function payoutResult(Request $request, HandlePayoutResult $handlePayoutResult): JsonResponse
    {
        $handlePayoutResult->handle($request->all());

        return $this->accepted();
    }

    public function payoutStatusResult(Request $request, CheckWithdrawalStatus $checkWithdrawalStatus): JsonResponse
    {
        $checkWithdrawalStatus->handleResult($request->all());

        return $this->accepted();
    }

    public function balanceResult(Request $request, BalanceReports $balanceReports): JsonResponse
    {
        $balanceReports->handleResult($request->all());

        return $this->accepted();
    }

    /**
     * A request timed out in Safaricom's queue. The outcome is unknown, so nothing changes: the withdrawal stays
     * processing until its result, a status check, or finance settles it.
     */
    public function queueTimeout(Request $request): JsonResponse
    {
        Log::channel('mpesa')->warning('M-Pesa queue timeout', ['path' => $request->path(), 'payload' => MpesaRedactor::redact($request->all())]);

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
