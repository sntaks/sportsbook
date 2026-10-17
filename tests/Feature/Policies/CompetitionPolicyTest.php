<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Competition;
use Database\Seeders\AdminRoleSeeder;

test('only super admins and managers may change which competitions are synced', function (AdminRole $role, bool $allowed) {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole($role)->create();

    expect([
        'viewAny' => $admin->can('viewAny', Competition::class),
        'update' => $admin->can('update', Competition::factory()->create()),
    ])->toBe(['viewAny' => true, 'update' => $allowed]);
})->with([
    'super admin' => [AdminRole::SuperAdmin, true],
    'manager' => [AdminRole::Manager, true],
    'trader' => [AdminRole::Trader, false],
    'finance' => [AdminRole::Finance, false],
    'support' => [AdminRole::Support, false],
]);

test('no admin may create or delete competitions, which come from the feed', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();

    expect($superAdmin->can('create', Competition::class))->toBeFalse()
        ->and($superAdmin->can('delete', Competition::factory()->create()))->toBeFalse();
});
