<?php

namespace App\Accounts;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;

/**
 * An admin suspends or reinstates a player. A suspended player can still log in and see their wallet, but cannot
 * deposit, withdraw or bet. Both are in the activity log with who did it and why.
 */
class PlayerStatus
{
    public function suspend(User $player, Admin $admin, string $reason): void
    {
        $player->forceFill(['status' => UserStatus::Suspended, 'suspension_reason' => mb_substr($reason, 0, 255)])->save();

        activity('players')
            ->causedBy($admin)
            ->performedOn($player)
            ->event('player-suspended')
            ->withProperties(['reason' => $reason])
            ->log('Player suspended');
    }

    public function reinstate(User $player, Admin $admin, string $reason): void
    {
        $player->forceFill(['status' => UserStatus::Active, 'suspension_reason' => null])->save();

        activity('players')
            ->causedBy($admin)
            ->performedOn($player)
            ->event('player-reinstated')
            ->withProperties(['reason' => $reason])
            ->log('Player reinstated');
    }
}
