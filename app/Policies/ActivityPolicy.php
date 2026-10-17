<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is read-only for everyone, super admins included.
 */
class ActivityPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ViewActivityLog->value);
    }

    public function view(Admin $admin, Activity $activity): bool
    {
        return $admin->can(AdminPermission::ViewActivityLog->value);
    }

    public function create(Admin $admin): bool
    {
        return false;
    }

    public function update(Admin $admin, Activity $activity): bool
    {
        return false;
    }

    public function delete(Admin $admin, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
