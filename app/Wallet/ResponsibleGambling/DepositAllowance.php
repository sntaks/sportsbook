<?php

namespace App\Wallet\ResponsibleGambling;

use App\Enums\MpesaDepositStatus;
use App\Models\DepositLimit;
use App\Models\MpesaDeposit;
use App\Models\User;

/**
 * How much more a player may deposit right now under their own limits.
 *
 * Each limit counts a rolling window (decision 28). Pending STK pushes count too, so several prompts sent at once
 * cannot add up to more than the limit.
 */
class DepositAllowance
{
    /**
     * Cents the player can still deposit, or null when no limit applies.
     */
    public function remaining(User $user): ?int
    {
        $remaining = null;

        foreach ($user->depositLimits()->get() as $limit) {
            $amount = $limit->effectiveAmount();

            if ($amount === null) {
                continue;
            }

            $left = max(0, $amount - $this->depositedWithin($user, $limit));
            $remaining = $remaining === null ? $left : min($remaining, $left);
        }

        return $remaining;
    }

    private function depositedWithin(User $user, DepositLimit $limit): int
    {
        return (int) MpesaDeposit::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [MpesaDepositStatus::Pending, MpesaDepositStatus::Completed])
            ->where('created_at', '>', now()->subHours($limit->period->hours()))
            ->sum('amount');
    }
}
