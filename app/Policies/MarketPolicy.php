<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Market;

/**
 * Suspending, reopening and overriding prices count as updating a market and need the "manage odds" permission.
 */
class MarketPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, Market $market): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Market $market): bool
    {
        return $admin->can(AdminPermission::ManageOdds->value);
    }

    public function delete(Admin $admin, Market $market): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
