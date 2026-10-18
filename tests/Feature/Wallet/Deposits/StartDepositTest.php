<?php

use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Wallet\Deposits\StartDeposit;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Safaricom accepts every push, numbering them ws_CO_1, ws_CO_2, …
 */
function fakeStkPushAccepted(): void
{
    $pushes = 0;

    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => function () use (&$pushes) {
        $pushes++;

        return Http::response([
            'MerchantRequestID' => "MR-{$pushes}",
            'CheckoutRequestID' => "ws_CO_{$pushes}",
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
        ]);
    }]);
}

/**
 * The validation message StartDeposit gives for the amount, or null if it did not refuse.
 */
function depositRefusal(Closure $start): ?string
{
    try {
        $start();
    } catch (ValidationException $e) {
        return $e->errors()['amount'][0] ?? null;
    }

    return null;
}

test('sends a push to the player\'s verified number and records the deposit as pending', function () {
    fakeMpesa();
    fakeStkPushAccepted();
    $user = User::factory()->create(['phone' => '254712345678']);

    $deposit = app(StartDeposit::class)->handle($user, '500');

    expect($deposit->status)->toBe(MpesaDepositStatus::Pending)
        ->and($deposit->amount)->toBe(50_000)
        ->and($deposit->phone)->toBe('254712345678')
        ->and($deposit->checkout_request_id)->toBe('ws_CO_1')
        ->and($deposit->merchant_request_id)->toBe('MR-1');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'stkpush')
        && $request['PhoneNumber'] === '254712345678'
        && $request['Amount'] === 500);
});

test('refuses amounts outside the deposit limits or with cents', function (string $amount, string $message) {
    fakeMpesa();

    expect(depositRefusal(fn () => app(StartDeposit::class)->handle(User::factory()->create(), $amount)))->toBe($message)
        ->and(MpesaDeposit::count())->toBe(0);
})->with([
    'below the minimum' => ['9', 'The minimum deposit is KES 10.'],
    'above the maximum' => ['150001', 'The maximum deposit is KES 150,000.'],
    'cents' => ['100.50', 'M-Pesa only takes whole shillings.'],
]);

test('refuses a player whose number is not verified', function () {
    fakeMpesa();

    expect(depositRefusal(fn () => app(StartDeposit::class)->handle(User::factory()->create(['phone_verified_at' => null]), '500')))
        ->toBe('Confirm your phone number before depositing.');
});

test('one push at a time: a second deposit waits until the first prompt has had time to finish', function () {
    fakeMpesa();
    fakeStkPushAccepted();
    $user = User::factory()->create();
    app(StartDeposit::class)->handle($user, '500');

    expect(depositRefusal(fn () => app(StartDeposit::class)->handle($user, '500')))
        ->toBe('A deposit is already waiting on your phone. Enter your M-Pesa PIN there, or wait a minute and try again.');

    $this->travel(91)->seconds();

    expect(depositRefusal(fn () => app(StartDeposit::class)->handle($user, '500')))->toBeNull();
});

test('caps pushes per player in a ten-minute window', function () {
    fakeMpesa();
    fakeStkPushAccepted();
    $user = User::factory()->create();

    foreach (range(1, 5) as $push) {
        app(StartDeposit::class)->handle($user, '100');
        $this->travel(91)->seconds();
    }

    expect(depositRefusal(fn () => app(StartDeposit::class)->handle($user, '100')))
        ->toBe('Too many deposit requests. Please try again in a few minutes.');
});

test('a push Safaricom rejects is recorded as failed and the player is told', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => Http::response(['errorCode' => '500.001.1001', 'errorMessage' => 'Unable to lock subscriber'], 500)]);
    $user = User::factory()->create();

    expect(depositRefusal(fn () => app(StartDeposit::class)->handle($user, '500')))
        ->toBe('M-Pesa could not start the payment. Please try again.');

    expect(MpesaDeposit::sole()->status)->toBe(MpesaDepositStatus::Failed);
});
