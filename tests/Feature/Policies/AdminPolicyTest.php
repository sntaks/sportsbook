<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;

test('only super admins may view, create and edit admin accounts', function (AdminRole $role, bool $allowed) {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole($role)->create();
    $otherAdmin = Admin::factory()->create();

    expect([
        'viewAny' => $admin->can('viewAny', Admin::class),
        'view' => $admin->can('view', $otherAdmin),
        'create' => $admin->can('create', Admin::class),
        'update' => $admin->can('update', $otherAdmin),
    ])->toBe([
        'viewAny' => $allowed,
        'view' => $allowed,
        'create' => $allowed,
        'update' => $allowed,
    ]);
})->with([
    'super admin' => [AdminRole::SuperAdmin, true],
    'manager' => [AdminRole::Manager, false],
    'trader' => [AdminRole::Trader, false],
    'finance' => [AdminRole::Finance, false],
    'support' => [AdminRole::Support, false],
]);

test('no admin may delete an admin account, super admins included', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $otherAdmin = Admin::factory()->create();

    expect($superAdmin->can('delete', $otherAdmin))->toBeFalse()
        ->and($superAdmin->can('deleteAny', Admin::class))->toBeFalse();
});
