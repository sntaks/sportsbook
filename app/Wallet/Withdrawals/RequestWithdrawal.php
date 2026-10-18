<?php

namespace App\Wallet\Withdrawals;

use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Wallet\InsufficientFunds;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A player asks to withdraw to their registered M-Pesa number.
 *
 * The amount leaves the wallet at once (the hold), in the same database transaction that records the withdrawal,
 * so it cannot be bet or withdrawn again while the payout is pending. Amounts at or above the approval threshold
 * wait for finance; the rest go straight to the payout job.
 */
class RequestWithdrawal
{
    public function __construct(private Ledger $ledger) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, mixed $amount): Withdrawal
    {
        $shillings = Validator::make(['amount' => $amount], [
            'amount' => ['required', 'integer', 'min:'.config('sportsbook.wallet.min_withdrawal'), 'max:'.config('sportsbook.wallet.max_withdrawal')],
        ], [
            'amount.integer' => __('M-Pesa only pays whole shillings.'),
            'amount.min' => __('The minimum withdrawal is KES :min.', ['min' => number_format((int) config('sportsbook.wallet.min_withdrawal'))]),
            'amount.max' => __('The maximum withdrawal is KES :max.', ['max' => number_format((int) config('sportsbook.wallet.max_withdrawal'))]),
        ])->safe()->integer('amount');

        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['amount' => __('Your account is suspended. Please contact support.')]);
        }

        if ($user->phone === null || $user->phone_verified_at === null) {
            throw ValidationException::withMessages(['amount' => __('Confirm your phone number before withdrawing.')]);
        }

        $limiterKey = 'withdrawal:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('sportsbook.wallet.withdrawals_per_hour'))) {
            throw ValidationException::withMessages(['amount' => __('Too many withdrawal requests. Please try again later.')]);
        }

        $cents = Money::fromShillings($shillings);
        $needsApproval = $shillings >= (int) config('sportsbook.wallet.withdrawal_approval_threshold');

        $withdrawal = DB::transaction(function () use ($user, $cents, $needsApproval): Withdrawal {
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'status' => $needsApproval ? WithdrawalStatus::AwaitingApproval : WithdrawalStatus::Queued,
                'phone' => $user->phone,
                'amount' => $cents,
            ]);

            try {
                $hold = $this->ledger->post(
                    $user,
                    WalletTransactionType::Withdrawal,
                    -$cents,
                    "withdrawal:{$withdrawal->id}",
                    source: $withdrawal,
                    description: __('M-Pesa withdrawal to :phone', ['phone' => PhoneNumber::mask($user->phone)]),
                );
            } catch (InsufficientFunds $e) {
                throw ValidationException::withMessages(['amount' => __('You can withdraw up to :amount.', ['amount' => Money::format($e->balance)])]);
            }

            $withdrawal->update(['hold_transaction_id' => $hold->id]);

            return $withdrawal;
        });

        RateLimiter::hit($limiterKey, 3600);

        if (! $needsApproval) {
            SendWithdrawalPayout::dispatch($withdrawal);
        }

        return $withdrawal;
    }
}
