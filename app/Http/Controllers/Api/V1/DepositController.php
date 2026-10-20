<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDepositRequest;
use App\Http\Resources\V1\DepositResource;
use App\Models\MpesaDeposit;
use App\Wallet\Deposits\StartDeposit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Wallet
 */
class DepositController extends Controller
{
    /**
     * Deposit with M-Pesa.
     *
     * Sends an M-Pesa payment prompt to the player's phone for `amount` whole shillings. The deposit starts pending:
     * poll it every few seconds until `is_pending` is false. Refused when the account cannot deposit, the amount is
     * out of bounds or over a deposit limit.
     */
    public function store(StoreDepositRequest $request, StartDeposit $startDeposit): JsonResponse
    {
        $deposit = $startDeposit->handle($request->user(), $request->input('amount'));

        return DepositResource::make($deposit)->response()->setStatusCode(201);
    }

    /**
     * A deposit.
     *
     * Another player's deposit is not found.
     *
     * @throws ModelNotFoundException
     */
    public function show(Request $request, int $deposit): DepositResource
    {
        return DepositResource::make(
            MpesaDeposit::query()->whereKey($deposit)->where('user_id', $request->user()->id)->firstOrFail(),
        );
    }
}
