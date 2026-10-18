<?php

namespace App\Http\Controllers;

use App\Wallet\Deposits\HandlePaybillConfirmation;
use App\Wallet\Deposits\HandleStkCallback;
use App\Wallet\Deposits\PaybillAccount;
use App\Wallet\Mpesa\MpesaRedactor;
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
     * number is a player's phone number and rejects the rest, so the payer gets their money back at once.
     */
    public function paybillValidation(Request $request): JsonResponse
    {
        Log::channel('mpesa')->info('Paybill validation', MpesaRedactor::redact($request->all()));

        if (PaybillAccount::player($request->input('BillRefNumber')) === null) {
            return response()->json(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
        }

        return $this->accepted();
    }

    public function paybillConfirmation(Request $request, HandlePaybillConfirmation $handlePaybillConfirmation): JsonResponse
    {
        $handlePaybillConfirmation->handle($request->all());

        return $this->accepted();
    }

    private function accepted(): JsonResponse
    {
        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
