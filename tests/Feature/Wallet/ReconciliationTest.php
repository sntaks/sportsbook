<?php

use App\Enums\MpesaDepositStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Wallet\Deposits\CompleteDeposit;
use App\Wallet\Ledger;
use App\Wallet\Reconciliation;

test('completed deposits and their ledger credits match for the day', function () {
    $this->travelTo(now()->setTimezone('Africa/Nairobi')->setTime(12, 0)->setTimezone('UTC'));
    $deposit = MpesaDeposit::factory()->create(['amount' => 50_000]);
    app(CompleteDeposit::class)->handle($deposit, 'TJK1A2B3C4');

    $today = app(Reconciliation::class)->days(1)[0];

    expect($today['mpesa_deposits'])->toBe(50_000)
        ->and($today['ledger_deposits'])->toBe(50_000);
});

test('a ledger deposit with no completed M-Pesa deposit behind it shows as a difference', function () {
    $this->travelTo(now()->setTimezone('Africa/Nairobi')->setTime(12, 0)->setTimezone('UTC'));
    app(Ledger::class)->post(User::factory()->create(), WalletTransactionType::Deposit, 10_000, 'stray-credit');

    $today = app(Reconciliation::class)->days(1)[0];

    expect($today['mpesa_deposits'])->toBe(0)
        ->and($today['ledger_deposits'])->toBe(10_000);
});

test('counts what needs a person', function () {
    MpesaDeposit::factory()->create(['status' => MpesaDepositStatus::Unmatched, 'user_id' => null, 'phone' => '']);
    MpesaDeposit::factory()->create(['status' => MpesaDepositStatus::Mismatch]);
    Withdrawal::factory()->create(['status' => WithdrawalStatus::AwaitingApproval]);
    Withdrawal::factory()->processing(minutesAgo: 45)->create();
    Withdrawal::factory()->processing(minutesAgo: 5)->create();

    expect(app(Reconciliation::class)->needsAttention())->toBe([
        'unmatched' => 1,
        'mismatch' => 1,
        'awaiting_approval' => 1,
        'stuck_payouts' => 1,
    ]);
});
