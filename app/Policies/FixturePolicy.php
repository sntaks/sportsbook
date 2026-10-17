<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Fixture;

/**
 * Fixtures come from the feed and are read-only in the admin panel.
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

    public function delete(Admin $admin, Fixture $fixture): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
