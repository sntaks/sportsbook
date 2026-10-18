<?php

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Database\Seeders\AdminRoleSeeder;

test('each role gets exactly its player and payments abilities', function (AdminRole $role, array $expected) {
    $this->seed(AdminRoleSeeder::class);
    $admin = Admin::factory()->withRole($role)->create();
    $player = User::factory()->create();

    expect([
        'view players' => $admin->can('view', $player),
        'suspend players' => $admin->can('update', $player),
        'adjust balances' => $admin->can('adjustBalance', $player),
        'view deposits' => $admin->can('view', MpesaDeposit::factory()->create()),
        'resolve deposits' => $admin->can('update', MpesaDeposit::factory()->create()),
        'view withdrawals' => $admin->can('view', Withdrawal::factory()->create()),
        'review withdrawals' => $admin->can('update', Withdrawal::factory()->create()),
        'view ledger' => $admin->can('viewAny', WalletTransaction::class),
        'reconcile' => $admin->can(AdminPermission::ReconcilePayments->value),
    ])->toBe($expected);
})->with([
    'super admin' => [AdminRole::SuperAdmin, ['view players' => true, 'suspend players' => true, 'adjust balances' => true, 'view deposits' => true, 'resolve deposits' => true, 'view withdrawals' => true, 'review withdrawals' => true, 'view ledger' => true, 'reconcile' => true]],
    'manager' => [AdminRole::Manager, ['view players' => true, 'suspend players' => true, 'adjust balances' => false, 'view deposits' => true, 'resolve deposits' => true, 'view withdrawals' => true, 'review withdrawals' => true, 'view ledger' => true, 'reconcile' => true]],
    'finance' => [AdminRole::Finance, ['view players' => true, 'suspend players' => false, 'adjust balances' => true, 'view deposits' => true, 'resolve deposits' => true, 'view withdrawals' => true, 'review withdrawals' => true, 'view ledger' => true, 'reconcile' => true]],
    'support' => [AdminRole::Support, ['view players' => true, 'suspend players' => false, 'adjust balances' => false, 'view deposits' => true, 'resolve deposits' => false, 'view withdrawals' => true, 'review withdrawals' => false, 'view ledger' => true, 'reconcile' => false]],
    'trader' => [AdminRole::Trader, ['view players' => false, 'suspend players' => false, 'adjust balances' => false, 'view deposits' => false, 'resolve deposits' => false, 'view withdrawals' => false, 'review withdrawals' => false, 'view ledger' => false, 'reconcile' => false]],
]);

test('nobody creates or deletes players, deposits, withdrawals or ledger entries in the admin panel', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();

    expect($superAdmin->can('create', User::class))->toBeFalse()
        ->and($superAdmin->can('delete', User::factory()->create()))->toBeFalse()
        ->and($superAdmin->can('create', MpesaDeposit::class))->toBeFalse()
        ->and($superAdmin->can('delete', MpesaDeposit::factory()->create()))->toBeFalse()
        ->and($superAdmin->can('create', Withdrawal::class))->toBeFalse()
        ->and($superAdmin->can('delete', Withdrawal::factory()->create()))->toBeFalse()
        ->and($superAdmin->can('update', WalletTransaction::factory()->create()))->toBeFalse();
});
