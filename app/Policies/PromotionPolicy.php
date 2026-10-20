<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\Promotion;

/**
 * Promotion pages are marketing copy players read, so only staff who may write it (managers) see them in the admin.
 */
class PromotionPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->can(AdminPermission::ManagePromotions->value);
    }

    public function view(Admin $admin, Promotion $promotion): bool
    {
        return $this->viewAny($admin);
    }

    public function create(Admin $admin): bool
    {
        return $this->viewAny($admin);
    }

    public function update(Admin $admin, Promotion $promotion): bool
    {
        return $this->viewAny($admin);
    }

    public function delete(Admin $admin, Promotion $promotion): bool
    {
        return $this->viewAny($admin);
    }

    public function deleteAny(Admin $admin): bool
    {
        return $this->viewAny($admin);
    }
}
