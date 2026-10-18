<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\MpesaDeposit;

/**
 * Deposits come from M-Pesa. Anyone who can see players or reconcile payments can view them; only those who
 * reconcile payments can act on them (check status, credit, dismiss).
 */
class MpesaDepositPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewPlayers->value) || $admin->can(AdminPermission::ReconcilePayments->value);
    }

    public function view(Admin $admin, MpesaDeposit $deposit): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, MpesaDeposit $deposit): bool
    {
        return $admin->can(AdminPermission::ReconcilePayments->value);
    }

    public function delete(Admin $admin, MpesaDeposit $deposit): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
