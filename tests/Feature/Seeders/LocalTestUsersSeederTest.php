<?php

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\LocalTestUsersSeeder;

test('seeds a player and one admin per role, and can be run again without duplicating them', function () {
    $this->seed(LocalTestUsersSeeder::class);
    $this->seed(LocalTestUsersSeeder::class);

    expect(User::where('email', 'player@sportsbook.test')->count())->toBe(1)
        ->and(Admin::count())->toBe(7)
        ->and(Admin::firstWhere('email', 'superadmin@sportsbook.test')->getRoleNames()->all())->toBe(['super-admin'])
        ->and(Admin::firstWhere('email', 'inactive@sportsbook.test')->is_active)->toBeFalse()
        ->and(Admin::firstWhere('email', 'newadmin@sportsbook.test')->getAppAuthenticationSecret())->toBeNull();
});

test('refuses to run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(LocalTestUsersSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'LocalTestUsersSeeder must never run in production.');

    $this->assertDatabaseCount('admins', 0);
});
