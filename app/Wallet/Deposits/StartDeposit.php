<?php

namespace App\Wallet\Deposits;

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Mpesa\MpesaApiException;
use App\Wallet\Mpesa\MpesaService;
use App\Wallet\ResponsibleGambling\DepositAllowance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Sends an STK push asking the player to pay a deposit from their own (verified) number.
 *
 * The deposit row is written before Safaricom is called, so every attempt is on record. If Safaricom does not
 * answer, the deposit is marked failed; should the player still have paid, the callback arrives with a
 * CheckoutRequestID we never saw and is kept as unmatched for admin (HandleStkCallback), never dropped.
 */
class StartDeposit
{
    public function __construct(private MpesaService $mpesa, private DepositAllowance $depositAllowance) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, mixed $amount): MpesaDeposit
    {
        $shillings = Validator::make(['amount' => $amount], [
            'amount' => ['required', 'integer', 'min:'.config('sportsbook.wallet.min_deposit'), 'max:'.config('sportsbook.wallet.max_deposit')],
        ], [
            'amount.integer' => __('M-Pesa only takes whole shillings.'),
            'amount.min' => __('The minimum deposit is KES :min.', ['min' => number_format((int) config('sportsbook.wallet.min_deposit'))]),
            'amount.max' => __('The maximum deposit is KES :max.', ['max' => number_format((int) config('sportsbook.wallet.max_deposit'))]),
        ])->safe()->integer('amount');

        if ($user->phone === null || $user->phone_verified_at === null) {
            throw ValidationException::withMessages(['amount' => __('Confirm your phone number before depositing.')]);
        }

        $this->ensureAllowedToDeposit($user, Money::fromShillings($shillings));
        $this->ensureNotWaitingOnAnotherPush($user);

        $limiterKey = 'stk-push:'.$user->id;

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('sportsbook.wallet.stk_pushes_per_window'))) {
            throw ValidationException::withMessages(['amount' => __('Too many deposit requests. Please try again in a few minutes.')]);
        }

        RateLimiter::hit($limiterKey, (int) config('sportsbook.wallet.stk_push_window_minutes') * 60);

        $deposit = MpesaDeposit::create([
            'user_id' => $user->id,
            'channel' => MpesaDepositChannel::Stk,
            'status' => MpesaDepositStatus::Pending,
            'phone' => $user->phone,
            'amount' => Money::fromShillings($shillings),
            'account_reference' => (string) config('app.name'),
        ]);

        try {
            $response = $this->mpesa->stkPush($shillings, $user->phone, (string) config('app.name'), __('Deposit'));
        } catch (MpesaApiException|ConnectionException|InvalidArgumentException $e) {
            Log::channel('mpesa')->error("STK push for deposit {$deposit->id} failed: {$e->getMessage()}");

            $this->fail($deposit, null, $e->getMessage());
        }

        if ((string) ($response['ResponseCode'] ?? '') !== '0' || ! is_string($response['CheckoutRequestID'] ?? null)) {
            $this->fail($deposit, (string) ($response['ResponseCode'] ?? ''), (string) ($response['ResponseDescription'] ?? 'No CheckoutRequestID'));
        }

        $deposit->update([
            'merchant_request_id' => is_string($response['MerchantRequestID'] ?? null) ? $response['MerchantRequestID'] : null,
            'checkout_request_id' => $response['CheckoutRequestID'],
        ]);

        return $deposit;
    }

    /**
     * Suspended and excluded players cannot deposit, and nobody can go over their own deposit limits.
     *
     * @throws ValidationException
     */
    private function ensureAllowedToDeposit(User $user, int $amount): void
    {
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['amount' => __('Your account is suspended. Please contact support.')]);
        }

        if ($user->isExcluded() && $user->excluded_until !== null) {
            throw ValidationException::withMessages(['amount' => __('Deposits are paused until :date while your break from betting runs.', [
                'date' => $user->excluded_until->timezone(config('sportsbook.timezone'))->format('j M Y, H:i'),
            ])]);
        }

        $allowance = $this->depositAllowance->remaining($user);

        if ($allowance !== null && $amount > $allowance) {
            throw ValidationException::withMessages(['amount' => $allowance < Money::fromShillings((int) config('sportsbook.wallet.min_deposit'))
                ? __('You have reached your deposit limit. It frees up as earlier deposits fall outside its period.')
                : __('That would take you over your deposit limit. You can deposit up to :amount right now.', ['amount' => Money::format($allowance)])]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotWaitingOnAnotherPush(User $user): void
    {
        $waiting = MpesaDeposit::query()
            ->pending()
            ->where('user_id', $user->id)
            ->where('channel', MpesaDepositChannel::Stk)
            ->where('created_at', '>', now()->subSeconds((int) config('sportsbook.wallet.stk_prompt_seconds')))
            ->exists();

        if ($waiting) {
            throw ValidationException::withMessages(['amount' => __('A deposit is already waiting on your phone. Enter your M-Pesa PIN there, or wait a minute and try again.')]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function fail(MpesaDeposit $deposit, ?string $code, string $description): never
    {
        $deposit->update([
            'status' => MpesaDepositStatus::Failed,
            'result_code' => $code,
            'result_description' => mb_substr($description, 0, 255),
        ]);

        throw ValidationException::withMessages(['amount' => __('M-Pesa could not start the payment. Please try again.')]);
    }
}
