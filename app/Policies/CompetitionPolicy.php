<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Competition;

/**
 * Competitions come from the feed. Admins may only change which are synced and their order.
 */
class CompetitionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    public function view(Admin $admin, Competition $competition): bool
    {
        return true;
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Competition $competition): bool
    {
        return $admin->can(AdminPermission::ManageCompetitions->value);
    }

    public function delete(Admin $admin, Competition $competition): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
