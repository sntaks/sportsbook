<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Spatie\Activitylog\Models\Activity;

test('only super admins and managers may read the activity log', function (AdminRole $role, bool $allowed) {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole($role)->create();
    $activity = activity('admin')->log('Something happened');

    expect([
        'viewAny' => $admin->can('viewAny', Activity::class),
        'view' => $admin->can('view', $activity),
    ])->toBe([
        'viewAny' => $allowed,
        'view' => $allowed,
    ]);
})->with([
    'super admin' => [AdminRole::SuperAdmin, true],
    'manager' => [AdminRole::Manager, true],
    'trader' => [AdminRole::Trader, false],
    'finance' => [AdminRole::Finance, false],
    'support' => [AdminRole::Support, false],
]);

test('no admin may create, edit or delete activity records, super admins included', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $activity = activity('admin')->log('Something happened');

    expect([
        'create' => $superAdmin->can('create', Activity::class),
        'update' => $superAdmin->can('update', $activity),
        'delete' => $superAdmin->can('delete', $activity),
        'deleteAny' => $superAdmin->can('deleteAny', Activity::class),
    ])->toBe([
        'create' => false,
        'update' => false,
        'delete' => false,
        'deleteAny' => false,
    ]);
});
