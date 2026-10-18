<?php

use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Wallet\Ledger;

/**
 * The body Safaricom posts to the Paybill confirmation and validation URLs. The payer's number arrives masked.
 */
function paybillPayment(string $accountNumber = '0712345678', string $amount = '250.00', string $transactionId = 'TJK9Z8Y7X6'): array
{
    return [
        'TransactionType' => 'Pay Bill',
        'TransID' => $transactionId,
        'TransTime' => '20261018101500',
        'TransAmount' => $amount,
        'BusinessShortCode' => '600000',
        'BillRefNumber' => $accountNumber,
        'MSISDN' => '2547 ***** 678',
        'FirstName' => 'Jane',
    ];
}

test('a Paybill payment to a player\'s number is credited to that player', function () {
    fakeMpesa();
    $user = User::factory()->create(['phone' => '254712345678']);

    $this->postJson(mpesaCallbackUrl('c2b/confirmation'), paybillPayment())
        ->assertOk()
        ->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    $deposit = MpesaDeposit::sole();

    expect($deposit->channel)->toBe(MpesaDepositChannel::Paybill)
        ->and($deposit->status)->toBe(MpesaDepositStatus::Completed)
        ->and($deposit->user->is($user))->toBeTrue()
        ->and($deposit->amount)->toBe(25_000)
        ->and($deposit->mpesa_receipt)->toBe('TJK9Z8Y7X6')
        ->and(app(Ledger::class)->balanceOf($user))->toBe(25_000);
});

test('the account number can be typed in any common format', function () {
    fakeMpesa();
    $user = User::factory()->create(['phone' => '254712345678']);

    $this->postJson(mpesaCallbackUrl('c2b/confirmation'), paybillPayment('254 712 345 678'))->assertOk();

    expect(app(Ledger::class)->balanceOf($user))->toBe(25_000);
});

test('a repeated confirmation pays nothing extra', function () {
    fakeMpesa();
    $user = User::factory()->create(['phone' => '254712345678']);

    $this->postJson(mpesaCallbackUrl('c2b/confirmation'), paybillPayment())->assertOk();
    $this->postJson(mpesaCallbackUrl('c2b/confirmation'), paybillPayment())->assertOk();

    expect(MpesaDeposit::count())->toBe(1)
        ->and(WalletTransaction::count())->toBe(1)
        ->and(app(Ledger::class)->balanceOf($user))->toBe(25_000);
});

test('a payment whose account number matches no player is kept as unmatched', function () {
    fakeMpesa();

    $this->postJson(mpesaCallbackUrl('c2b/confirmation'), paybillPayment('0799000000'))->assertOk();

    $deposit = MpesaDeposit::sole();

    expect($deposit->status)->toBe(MpesaDepositStatus::Unmatched)
        ->and($deposit->user_id)->toBeNull()
        ->and($deposit->account_reference)->toBe('0799000000')
        ->and(WalletTransaction::count())->toBe(0);
});

test('validation accepts a player\'s number and rejects anything else', function (string $accountNumber, array $expected) {
    fakeMpesa();
    User::factory()->create(['phone' => '254712345678']);

    $this->postJson(mpesaCallbackUrl('c2b/validation'), paybillPayment($accountNumber))
        ->assertOk()
        ->assertExactJson($expected);
})->with([
    'a player' => ['0712345678', ['ResultCode' => 0, 'ResultDesc' => 'Accepted']],
    'nobody' => ['0799000000', ['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']],
    'not a number' => ['INVOICE-42', ['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']],
]);

test('Paybill callbacks with the wrong secret get a 404', function () {
    fakeMpesa();
    User::factory()->create(['phone' => '254712345678']);

    $this->postJson('/api/mpesa/wrong-secret-wrong-secret-wrong-secret-12/c2b/confirmation', paybillPayment())->assertNotFound();

    expect(MpesaDeposit::count())->toBe(0);
});
