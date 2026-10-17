<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;

/**
 * Admin accounts are managed only by holders of the "manage admins" permission. They are deactivated, never deleted,
 * so the audit trail always resolves to a real account.
 */
class AdminPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ManageAdmins->value);
    }

    public function view(Admin $admin, Admin $model): bool
    {
        return $admin->can(AdminPermission::ManageAdmins->value);
    }

    public function create(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ManageAdmins->value);
    }

    public function update(Admin $admin, Admin $model): bool
    {
        return $admin->can(AdminPermission::ManageAdmins->value);
    }

    public function delete(Admin $admin, Admin $model): bool
    {
        return false;
    }

    public function deleteAny(Admin $admin): bool
    {
        return false;
    }
}
