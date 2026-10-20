<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Jackpot;

/**
 * Staff who can look up bets can look up jackpots and their entries; managers and traders draft, publish, cancel
 * and settle them. Jackpots are never deleted: their entries hold money.
 */
class JackpotPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewBets->value) || $admin->can(AdminPermission::ManageJackpots->value);
    }

    public function view(Admin $admin, Jackpot $jackpot): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ManageJackpots->value);
    }

    /**
     * Change a draft, publish, cancel or settle the jackpot.
     */
    public function update(Admin $admin, Jackpot $jackpot): bool
    {
        return $admin->can(AdminPermission::ManageJackpots->value);
    }

    public function delete(Admin $admin, Jackpot $jackpot): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
