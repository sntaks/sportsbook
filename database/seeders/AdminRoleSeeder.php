<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleSeeder extends Seeder
{
    /**
     * Permissions granted to each role. Super admins are granted everything through Gate::before.
     *
     * @var array<string, list<AdminPermission>>
     */
    private const ROLE_PERMISSIONS = [
        'manager' => [AdminPermission::ViewActivityLog, AdminPermission::ManageCompetitions, AdminPermission::ManageOdds],
        'trader' => [AdminPermission::ManageOdds],
    ];

    /**
     * Create every back-office role and permission on the admin guard. Safe to run repeatedly.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'admin');
        }

        foreach (AdminRole::cases() as $role) {
            Role::findOrCreate($role->value, 'admin')->syncPermissions(
                array_map(fn (AdminPermission $permission): string => $permission->value, self::ROLE_PERMISSIONS[$role->value] ?? []),
            );
        }
    }
}
