<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\User;

/**
 * What admins may do with player accounts (the User model) in the admin panel. Players are never created or
 * deleted there: they sign up themselves, and accounts that have moved money are closed, not deleted.
 */
class PlayerPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewPlayers->value);
    }

    public function view(Admin $admin, User $player): bool
    {
        return $admin->can(AdminPermission::ViewPlayers->value);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Suspending and reinstating.
     */
    public function update(Admin $admin, User $player): bool
    {
        return $admin->can(AdminPermission::ManagePlayers->value);
    }

    public function adjustBalance(Admin $admin, User $player): bool
    {
        return $admin->can(AdminPermission::AdjustBalances->value);
    }

    public function delete(Admin $admin, User $player): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
