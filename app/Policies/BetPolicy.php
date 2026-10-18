<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Bet;

/**
 * Staff who handle players can look up bets; traders and managers cancel them or void a leg. Bets are only ever
 * placed by players and never deleted.
 */
class BetPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewBets->value);
    }

    public function view(Admin $admin, Bet $bet): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Cancel the bet or void one of its legs.
     */
    public function update(Admin $admin, Bet $bet): bool
    {
        return $admin->can(AdminPermission::SettleBets->value);
    }

    public function delete(Admin $admin, Bet $bet): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
