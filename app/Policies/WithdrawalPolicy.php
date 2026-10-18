<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Withdrawal;

/**
 * Anyone who can see players or approve withdrawals can view withdrawals; only those who approve withdrawals can
 * approve, reject, check or settle one by hand.
 */
class WithdrawalPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewPlayers->value) || $admin->can(AdminPermission::ApproveWithdrawals->value);
    }

    public function view(Admin $admin, Withdrawal $withdrawal): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Withdrawal $withdrawal): bool
    {
        return $admin->can(AdminPermission::ApproveWithdrawals->value);
    }

    public function delete(Admin $admin, Withdrawal $withdrawal): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
