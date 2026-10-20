<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\FreeBet;

/**
 * Staff who can see players see their free bets; managers and finance void unused ones. Free bets are given from
 * the player's page (PlayerPolicy::grantFreeBet) or by the system, and never edited or deleted.
 */
class FreeBetPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewPlayers->value);
    }

    public function view(Admin $admin, FreeBet $freeBet): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Void a free bet the player has not used.
     */
    public function update(Admin $admin, FreeBet $freeBet): bool
    {
        return $admin->can(AdminPermission::GrantFreeBets->value);
    }

    public function delete(Admin $admin, FreeBet $freeBet): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
