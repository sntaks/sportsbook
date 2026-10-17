<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Spatie\Activitylog\Models\Activity;

test('records a role granted to an admin', function () {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->create();

    $admin->assignRole(AdminRole::Trader->value);

    $activity = Activity::query()->where('event', 'role-attached')->sole();
    expect($activity->subject->is($admin))->toBeTrue()
        ->and($activity->properties->get('roles'))->toBe(['trader']);
});

test('records a role removed from an admin', function () {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole(AdminRole::Finance)->create();

    $admin->removeRole(AdminRole::Finance->value);

    $activity = Activity::query()->where('event', 'role-detached')->sole();
    expect($activity->subject->is($admin))->toBeTrue()
        ->and($activity->properties->get('roles'))->toBe(['finance']);
});
