<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWithdrawalRequest;
use App\Http\Resources\V1\WithdrawalResource;
use App\Models\Withdrawal;
use App\Wallet\Withdrawals\RequestWithdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Wallet
 */
class WithdrawalController extends Controller
{
    /**
     * My withdrawals.
     *
     * Newest first, 20 a page.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return WithdrawalResource::collection(
            Withdrawal::query()->where('user_id', $request->user()->id)->latest('id')->paginate(20),
        );
    }

    /**
     * Withdraw to M-Pesa.
     *
     * Pays `amount` whole shillings to the player's M-Pesa number. The money leaves the balance at once. Amounts at
     * or above the approval threshold wait for review (status `awaiting-approval`) before they are paid.
     */
    public function store(StoreWithdrawalRequest $request, RequestWithdrawal $requestWithdrawal): JsonResponse
    {
        $withdrawal = $requestWithdrawal->handle($request->user(), $request->input('amount'));

        return WithdrawalResource::make($withdrawal)->response()->setStatusCode(201);
    }
}
