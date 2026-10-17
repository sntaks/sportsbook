<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Fixture;
use Database\Seeders\AdminRoleSeeder;

test('fixtures are read-only for every admin, super admins included', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $fixture = Fixture::factory()->create();

    expect([
        'view' => $superAdmin->can('view', $fixture),
        'create' => $superAdmin->can('create', Fixture::class),
        'update' => $superAdmin->can('update', $fixture),
        'delete' => $superAdmin->can('delete', $fixture),
    ])->toBe(['view' => true, 'create' => false, 'update' => false, 'delete' => false]);
});
