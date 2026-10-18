<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Fixture;

/**
 * Fixtures come from the feed and are read-only in the admin panel, apart from results, which traders and managers
 * enter or correct.
 */
class FixturePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, Fixture $fixture): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Fixture $fixture): bool
    {
        return false;
    }

    /**
     * Enter, correct or void the result bets on the fixture settle on.
     */
    public function settle(Admin $admin, Fixture $fixture): bool
    {
        return $admin->can(AdminPermission::SettleBets->value);
    }

    public function delete(Admin $admin, Fixture $fixture): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
