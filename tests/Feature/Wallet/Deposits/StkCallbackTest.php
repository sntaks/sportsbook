<?php

use App\Enums\MpesaDepositStatus;
use App\Enums\WalletTransactionType;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Wallet\Ledger;

function pendingDeposit(array $attributes = []): MpesaDeposit
{
    return MpesaDeposit::factory()
        ->for(User::factory()->create(['phone' => '254712345678']))
        ->create(['checkout_request_id' => 'ws_CO_1', 'amount' => 50_000, ...$attributes]);
}

test('a successful callback credits the player once and records the receipt', function () {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))
        ->assertOk()
        ->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    $deposit->refresh();

    expect($deposit->status)->toBe(MpesaDepositStatus::Completed)
        ->and($deposit->mpesa_receipt)->toBe('TJK1A2B3C4')
        ->and($deposit->walletTransaction->type)->toBe(WalletTransactionType::Deposit)
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(50_000);
});

test('a repeated callback pays nothing extra', function () {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();
    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();

    expect(app(Ledger::class)->balanceOf($deposit->user))->toBe(50_000)
        ->and(WalletTransaction::count())->toBe(1);
});

test('a payment of a different amount or from a different phone is held for review, not credited', function (array $paid) {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1', ...$paid))->assertOk();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Mismatch)
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(0);
})->with([
    'different amount' => [['amount' => 5]],
    'different phone' => [['phone' => '254799999999']],
]);

test('a cancelled prompt marks the deposit cancelled and credits nothing', function () {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1', resultCode: 1032))->assertOk();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Cancelled)
        ->and($deposit->fresh()->result_code)->toBe('1032')
        ->and(WalletTransaction::count())->toBe(0);
});

test('any other failure marks the deposit failed', function () {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1', resultCode: 1037))->assertOk();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Failed);
});

test('a success that arrives after the deposit expired is still credited', function () {
    fakeMpesa();
    $deposit = pendingDeposit(['status' => MpesaDepositStatus::Expired]);

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Completed)
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(50_000);
});

test('a paid callback for a push we have no record of is kept as unmatched, not dropped', function () {
    fakeMpesa();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_unknown'))->assertOk();

    $deposit = MpesaDeposit::sole();

    expect($deposit->status)->toBe(MpesaDepositStatus::Unmatched)
        ->and($deposit->user_id)->toBeNull()
        ->and($deposit->amount)->toBe(50_000)
        ->and($deposit->mpesa_receipt)->toBe('TJK1A2B3C4');
});

test('the stored callback has the phone number redacted', function () {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();

    expect(json_encode($deposit->fresh()->callback_payload))->not->toContain('254712345678');
});

test('a callback with the wrong secret gets a 404 and changes nothing', function () {
    fakeMpesa();
    $deposit = pendingDeposit();

    $this->postJson('/api/mpesa/wrong-secret-wrong-secret-wrong-secret-12/stk', stkCallback('ws_CO_1'))->assertNotFound();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Pending);
});

test('with an IP allowlist set, callbacks from other addresses get a 404', function () {
    fakeMpesa();
    config(['mpesa.allowed_ips' => ['196.201.214.200']]);
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '196.201.214.200'])
        ->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))
        ->assertOk();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Completed);
});

test('callbacks are refused while no callback secret is configured', function () {
    fakeMpesa();
    config(['mpesa.callback_secret' => null]);

    $this->postJson('/api/mpesa//stk', stkCallback('ws_CO_1'))->assertNotFound();
});

test('a deposit tax, when configured, is posted as its own entry', function () {
    fakeMpesa();
    config(['sportsbook.wallet.deposit_tax_rate' => 0.05]);
    $deposit = pendingDeposit();

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();

    expect(WalletTransaction::query()->orderBy('id')->pluck('amount')->all())->toBe([50_000, -2_500])
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(47_500);
});
