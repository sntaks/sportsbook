<?php

use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Withdrawal;
use App\Wallet\Ledger;
use App\Wallet\Withdrawals\RequestWithdrawal;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function withdrawalRefusal(Closure $request): ?string
{
    try {
        $request();
    } catch (ValidationException $e) {
        return $e->errors()['amount'][0] ?? null;
    }

    return null;
}

test('holds the money at once and queues the payout', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(100_000);

    $withdrawal = app(RequestWithdrawal::class)->handle($player, '500');

    expect($withdrawal->status)->toBe(WithdrawalStatus::Queued)
        ->and($withdrawal->amount)->toBe(50_000)
        ->and($withdrawal->phone)->toBe('254712345678')
        ->and(app(Ledger::class)->balanceOf($player))->toBe(50_000);
    expect($player->walletTransactions()->latest('id')->first())
        ->type->toBe(WalletTransactionType::Withdrawal)
        ->amount->toBe(-50_000)
        ->idempotency_key->toBe("withdrawal:{$withdrawal->id}");
    Queue::assertPushed(SendWithdrawalPayout::class, fn (SendWithdrawalPayout $job): bool => $job->withdrawal->is($withdrawal));
});

test('KES 20,000 or more waits for finance instead of paying out', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(3_000_000);

    $withdrawal = app(RequestWithdrawal::class)->handle($player, '20000');

    expect($withdrawal->status)->toBe(WithdrawalStatus::AwaitingApproval)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(1_000_000);
    Queue::assertNotPushed(SendWithdrawalPayout::class);
});

test('cannot withdraw more than the balance, and nothing is recorded', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(10_000);

    expect(withdrawalRefusal(fn () => app(RequestWithdrawal::class)->handle($player, '101')))->toBe('You can withdraw up to KES 100.00.')
        ->and(Withdrawal::count())->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(10_000);
});

test('refuses amounts outside the withdrawal limits or with cents', function (string $amount, string $message) {
    Queue::fake([SendWithdrawalPayout::class]);

    expect(withdrawalRefusal(fn () => app(RequestWithdrawal::class)->handle(fundedPlayer(100_000_000), $amount)))->toBe($message);
})->with([
    'below the minimum' => ['49', 'The minimum withdrawal is KES 50.'],
    'above the maximum' => ['150001', 'The maximum withdrawal is KES 150,000.'],
    'cents' => ['100.50', 'M-Pesa only pays whole shillings.'],
]);

test('a suspended player cannot withdraw', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(100_000, ['status' => UserStatus::Suspended]);

    expect(withdrawalRefusal(fn () => app(RequestWithdrawal::class)->handle($player, '500')))->toBe('Your account is suspended. Please contact support.');
});

test('a player on a break from betting can still withdraw', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(100_000, ['excluded_until' => now()->addMonth()]);

    expect(app(RequestWithdrawal::class)->handle($player, '500')->status)->toBe(WithdrawalStatus::Queued);
});

test('caps withdrawal requests at five an hour', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(1_000_000);

    foreach (range(1, 5) as $request) {
        app(RequestWithdrawal::class)->handle($player, '100');
    }

    expect(withdrawalRefusal(fn () => app(RequestWithdrawal::class)->handle($player, '100')))->toBe('Too many withdrawal requests. Please try again later.');
});
