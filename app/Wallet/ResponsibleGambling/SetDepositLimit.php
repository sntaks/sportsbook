<?php

namespace App\Wallet\ResponsibleGambling;

use App\Enums\DepositLimitPeriod;
use App\Models\DepositLimit;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A player sets, raises, cuts or removes one of their deposit limits.
 *
 * A first limit or a cut applies at once. A raise or a removal waits `limit_raise_delay_hours` (24) before it
 * takes over, so a limit cannot be lifted in the heat of the moment. Every change is in the activity log.
 */
class SetDepositLimit
{
    /**
     * @param  mixed  $shillings  The new limit in whole shillings, or null/'' to remove it.
     *
     * @throws ValidationException
     */
    public function handle(User $user, DepositLimitPeriod $period, mixed $shillings): DepositLimit
    {
        $new = blank($shillings) ? null : Money::fromShillings(Validator::make(['amount' => $shillings], [
            'amount' => ['integer', 'min:'.config('sportsbook.wallet.min_deposit'), 'max:100000000'],
        ], [
            'amount.integer' => __('Enter the limit in whole shillings.'),
            'amount.min' => __('A limit must be at least KES :min.', ['min' => number_format((int) config('sportsbook.wallet.min_deposit'))]),
        ])->safe()->integer('amount'));

        $limit = DepositLimit::query()->firstOrNew(['user_id' => $user->id, 'period' => $period]);
        $current = $limit->exists ? $limit->effectiveAmount() : null;
        $isCut = $new !== null && ($current === null || $new < $current);

        if ($isCut || $new === $current) {
            $limit->fill(['amount' => $new, 'pending_amount' => null, 'has_pending_change' => false, 'pending_from' => null]);
        } else {
            $limit->fill([
                'amount' => $current,
                'pending_amount' => $new,
                'has_pending_change' => true,
                'pending_from' => now()->addHours((int) config('sportsbook.wallet.limit_raise_delay_hours')),
            ]);
        }

        $limit->save();

        activity('responsible-gambling')
            ->causedBy($user)
            ->performedOn($user)
            ->event('deposit-limit-changed')
            ->withProperties([
                'period' => $period->value,
                'from' => $current,
                'to' => $new,
                'applies_from' => ($isCut || $new === $current) ? now()->toIso8601String() : $limit->pending_from?->toIso8601String(),
            ])
            ->log('Deposit limit changed');

        return $limit;
    }
}
