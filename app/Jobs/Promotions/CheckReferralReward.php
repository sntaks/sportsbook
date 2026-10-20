<?php

namespace App\Jobs\Promotions;

use App\Models\User;
use App\Promotions\RewardReferral;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks whether an invited player now qualifies their referrer for a reward. Queued after a deposit, bet or jackpot
 * entry commits, so it never slows those down or sees money that was rolled back. Safe to run any number of times.
 */
class CheckReferralReward implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public User $player)
    {
        $this->afterCommit();
    }

    /**
     * Queue a check, only for a player who could still qualify someone.
     */
    public static function dispatchFor(User $player): void
    {
        if (RewardReferral::mightApply($player)) {
            self::dispatch($player);
        }
    }

    public function handle(RewardReferral $rewardReferral): void
    {
        $rewardReferral->handle($this->player);
    }
}
