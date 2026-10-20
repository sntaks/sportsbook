<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\WalletTransactionResource;
use App\Models\WalletTransaction;
use App\Wallet\Ledger;
use App\Wallet\ResponsibleGambling\DepositAllowance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Wallet
 */
class WalletController extends Controller
{
    /**
     * The wallet.
     *
     * Balance, what the player's deposit limits still allow, the deposit and withdrawal bounds (in whole shillings)
     * and, when M-Pesa Paybill is set up, the numbers to pay from the M-Pesa menu.
     */
    public function show(Request $request, Ledger $ledger, DepositAllowance $depositAllowance): JsonResponse
    {
        $user = $request->user();
        $paybill = config('mpesa.c2b.short_code');

        return response()->json([
            'balance_cents' => $ledger->balanceOf($user),
            /** Cents the deposit limits still allow, or null when no limit is set. */
            'deposit_allowance_cents' => $depositAllowance->remaining($user),
            'can_deposit' => $user->canDeposit(),
            'can_withdraw' => $user->canWithdraw(),
            'min_deposit' => (int) config('sportsbook.wallet.min_deposit'),
            'max_deposit' => (int) config('sportsbook.wallet.max_deposit'),
            'min_withdrawal' => (int) config('sportsbook.wallet.min_withdrawal'),
            'max_withdrawal' => (int) config('sportsbook.wallet.max_withdrawal'),
            /** Withdrawals of this many shillings or more wait for approval. */
            'withdrawal_approval_threshold' => (int) config('sportsbook.wallet.withdrawal_approval_threshold'),
            /** The numbers to pay from the M-Pesa menu, or null when Paybill is not set up. */
            'paybill' => filled($paybill) ? [
                'business_number' => (string) $paybill,
                'account_number' => '0'.substr((string) $user->phone, 3),
            ] : null,
        ]);
    }

    /**
     * Wallet transactions.
     *
     * Every movement of money, newest first, 20 a page.
     */
    public function transactions(Request $request): AnonymousResourceCollection
    {
        $transactions = WalletTransaction::query()
            ->whereHas('wallet', fn ($wallet) => $wallet->where('user_id', $request->user()->id))
            ->latest('id')
            ->paginate(20);

        return WalletTransactionResource::collection($transactions);
    }
}
