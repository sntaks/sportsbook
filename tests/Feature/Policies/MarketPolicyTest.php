<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Market;
use Database\Seeders\AdminRoleSeeder;

test('only super admins, managers and traders may suspend, reopen or override markets', function (AdminRole $role, bool $allowed) {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole($role)->create();

    expect([
        'view' => $admin->can('view', $market = Market::factory()->create()),
        'update' => $admin->can('update', $market),
    ])->toBe(['view' => true, 'update' => $allowed]);
})->with([
    'super admin' => [AdminRole::SuperAdmin, true],
    'manager' => [AdminRole::Manager, true],
    'trader' => [AdminRole::Trader, true],
    'finance' => [AdminRole::Finance, false],
    'support' => [AdminRole::Support, false],
]);

test('no admin may create or delete markets, which come from the feed', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();

    expect($superAdmin->can('create', Market::class))->toBeFalse()
        ->and($superAdmin->can('delete', Market::factory()->create()))->toBeFalse();
});
