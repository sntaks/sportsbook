<?php

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Wallet\IdempotencyConflict;
use App\Wallet\InsufficientFunds;
use App\Wallet\Ledger;

test('a credit creates the wallet and records the balance after it', function () {
    $user = User::factory()->create();

    $entry = app(Ledger::class)->post($user, WalletTransactionType::Deposit, 50_000, 'test:1');

    expect($entry->amount)->toBe(50_000)
        ->and($entry->balance_after)->toBe(50_000)
        ->and($user->wallet->balance)->toBe(50_000)
        ->and(app(Ledger::class)->balanceOf($user))->toBe(50_000);
});

test('a debit takes money out and the history adds up to the balance', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);

    $ledger->post($user, WalletTransactionType::Deposit, 50_000, 'test:1');
    $debit = $ledger->post($user, WalletTransactionType::Adjustment, -20_000, 'test:2');

    expect($debit->balance_after)->toBe(30_000)
        ->and($user->wallet->fresh()->balance)->toBe(30_000)
        ->and((int) $user->wallet->transactions()->sum('amount'))->toBe(30_000);
});

test('refuses to take the balance below zero', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    $ledger->post($user, WalletTransactionType::Deposit, 10_000, 'test:1');

    expect(fn () => $ledger->post($user, WalletTransactionType::Adjustment, -10_001, 'test:2'))
        ->toThrow(InsufficientFunds::class);

    expect($ledger->balanceOf($user))->toBe(10_000)
        ->and(WalletTransaction::count())->toBe(1);
});

test('posting the same key again returns the first entry and moves no money', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);

    $first = $ledger->post($user, WalletTransactionType::Deposit, 50_000, 'mpesa-deposit:1');
    $again = $ledger->post($user, WalletTransactionType::Deposit, 50_000, 'mpesa-deposit:1');

    expect($again->is($first))->toBeTrue()
        ->and($ledger->balanceOf($user))->toBe(50_000)
        ->and(WalletTransaction::count())->toBe(1);
});

test('reusing a key for a different entry is refused', function (Closure $post) {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    $ledger->post($user, WalletTransactionType::Deposit, 50_000, 'mpesa-deposit:1');

    expect(fn () => $post($ledger, $user))->toThrow(IdempotencyConflict::class);
})->with([
    'different amount' => [fn (Ledger $ledger, User $user) => $ledger->post($user, WalletTransactionType::Deposit, 60_000, 'mpesa-deposit:1')],
    'different type' => [fn (Ledger $ledger, User $user) => $ledger->post($user, WalletTransactionType::Adjustment, 50_000, 'mpesa-deposit:1')],
    'different player' => [fn (Ledger $ledger) => $ledger->post(User::factory()->create(), WalletTransactionType::Deposit, 50_000, 'mpesa-deposit:1')],
]);

test('refuses an entry that moves no money', function () {
    expect(fn () => app(Ledger::class)->post(User::factory()->create(), WalletTransactionType::Adjustment, 0, 'test:1'))
        ->toThrow(InvalidArgumentException::class);
});

test('ledger entries can be neither changed nor deleted', function (Closure $tamper) {
    $entry = app(Ledger::class)->post(User::factory()->create(), WalletTransactionType::Deposit, 50_000, 'test:1');

    expect(fn () => $tamper($entry))->toThrow(LogicException::class);

    expect($entry->fresh()->amount)->toBe(50_000);
})->with([
    'changed' => [fn (WalletTransaction $entry) => $entry->forceFill(['amount' => 1])->save()],
    'deleted' => [fn (WalletTransaction $entry) => $entry->delete()],
]);

test('a player who has never deposited has a balance of zero', function () {
    expect(app(Ledger::class)->balanceOf(User::factory()->create()))->toBe(0);
});
