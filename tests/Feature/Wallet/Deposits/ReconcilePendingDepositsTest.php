<?php

use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Wallet\Deposits\ReconcilePendingDeposits;
use App\Wallet\Ledger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function staleDeposit(int $minutesAgo = 2): MpesaDeposit
{
    return MpesaDeposit::factory()
        ->for(User::factory()->create(['phone' => '254712345678']))
        ->startedMinutesAgo($minutesAgo)
        ->create(['checkout_request_id' => 'ws_CO_1', 'amount' => 50_000]);
}

function fakeStkQuery(array $body, int $status = 200): void
{
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpushquery/*' => Http::response($body, $status)]);
}

test('a deposit the status check reports as paid is credited', function () {
    fakeMpesa();
    fakeStkQuery(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.']);
    $deposit = staleDeposit();

    expect(app(ReconcilePendingDeposits::class)->handle()['completed'])->toBe(1);

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Completed)
        ->and($deposit->fresh()->mpesa_receipt)->toBeNull()
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(50_000);
});

test('the callback arriving after the status check fills in the receipt without paying twice', function () {
    fakeMpesa();
    fakeStkQuery(['ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.']);
    $deposit = staleDeposit();
    app(ReconcilePendingDeposits::class)->handle();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();

    expect($deposit->fresh()->mpesa_receipt)->toBe('TJK1A2B3C4')
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(50_000)
        ->and(WalletTransaction::count())->toBe(1);
});

test('a prompt the player cancelled is closed as cancelled', function () {
    fakeMpesa();
    fakeStkQuery(['ResultCode' => '1032', 'ResultDesc' => 'Request cancelled by user']);
    $deposit = staleDeposit();

    app(ReconcilePendingDeposits::class)->handle();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Cancelled)
        ->and(WalletTransaction::count())->toBe(0);
});

test('a push Safaricom is still processing stays pending and is not asked about again within a minute', function () {
    fakeMpesa();
    fakeStkQuery(['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], 500);
    $deposit = staleDeposit();

    expect(app(ReconcilePendingDeposits::class)->handle()['waiting'])->toBe(1);
    app(ReconcilePendingDeposits::class)->handle();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Pending)
        ->and($deposit->fresh()->query_attempts)->toBe(1);
    Http::assertSentCount(2);
});

test('a push younger than a minute is left alone', function () {
    fakeMpesa();
    $deposit = MpesaDeposit::factory()->create(['checkout_request_id' => 'ws_CO_1']);

    app(ReconcilePendingDeposits::class)->handle();

    expect($deposit->fresh()->query_attempts)->toBe(0);
    Http::assertNothingSent();
});

test('a push still unanswered after thirty minutes expires without asking Safaricom', function () {
    fakeMpesa();
    $deposit = staleDeposit(minutesAgo: 31);

    expect(app(ReconcilePendingDeposits::class)->handle()['expired'])->toBe(1);

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Expired);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'stkpushquery'));
});

test('the schedule runs the reconciliation every minute', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'wallet:reconcile-deposits'));

    expect($event?->expression)->toBe('* * * * *');
});
