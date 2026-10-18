<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\WalletTransaction;

/**
 * The ledger is read-only everywhere: entries are only ever written by App\Wallet\Ledger.
 */
class WalletTransactionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewPlayers->value);
    }

    public function view(Admin $admin, WalletTransaction $transaction): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, WalletTransaction $transaction): bool
    {
        return false;
    }

    public function delete(Admin $admin, WalletTransaction $transaction): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
