<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Bet;
use Database\Seeders\AdminRoleSeeder;

test('every role may view bets, but only trading roles may settle, cancel or void them', function (AdminRole $role, bool $canSettle) {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole($role)->create();
    $fixture = pricedFixture();
    $bet = betOn(bettor(), [[$fixture, 'home']]);

    expect([
        'viewAny' => $admin->can('viewAny', Bet::class),
        'view' => $admin->can('view', $bet),
        'update' => $admin->can('update', $bet),
        'settle fixture' => $admin->can('settle', $fixture),
    ])->toBe([
        'viewAny' => true,
        'view' => true,
        'update' => $canSettle,
        'settle fixture' => $canSettle,
    ]);
})->with([
    'super admin' => [AdminRole::SuperAdmin, true],
    'manager' => [AdminRole::Manager, true],
    'trader' => [AdminRole::Trader, true],
    'finance' => [AdminRole::Finance, false],
    'support' => [AdminRole::Support, false],
]);

test('no admin may create or delete a bet', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $bet = betOn(bettor(), [[pricedFixture(), 'home']]);

    expect($superAdmin->can('create', Bet::class))->toBeFalse()
        ->and($superAdmin->can('delete', $bet))->toBeFalse()
        ->and($superAdmin->can('deleteAny', Bet::class))->toBeFalse();
});

test('an admin without a role cannot see bets', function () {
    $admin = Admin::factory()->create();

    expect($admin->can('viewAny', Bet::class))->toBeFalse();
});
