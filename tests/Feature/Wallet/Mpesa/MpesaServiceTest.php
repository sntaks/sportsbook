<?php

use App\Wallet\Mpesa\MpesaApiException;
use App\Wallet\Mpesa\MpesaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('an STK push sends one timestamp, in Nairobi time, in both the password and the timestamp field', function () {
    fakeMpesa();
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 18)->setTime(7, 15, 59));
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => Http::response(['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_CO_1'])]);

    app(MpesaService::class)->stkPush(500, '254712345678', 'SportsBook', 'Deposit');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'stkpush')) {
            return false;
        }

        return $request['Timestamp'] === '20261018101559'
            && $request['Password'] === base64_encode('174379test-passkey20261018101559')
            && $request['Amount'] === 500
            && $request['PhoneNumber'] === '254712345678'
            && $request['CallBackURL'] === 'https://callbacks.sportsbook.test/api/mpesa/'.MPESA_TEST_SECRET.'/stk'
            && $request->hasHeader('Authorization', 'Bearer test-token');
    });
});

test('talks to the sandbox or production host according to MPESA_ENV, not APP_ENV', function (string $environment, string $host) {
    fakeMpesa();
    config(['mpesa.environment' => $environment]);
    app()->detectEnvironment(fn (): string => 'production');
    Http::fake([
        "{$host}/oauth/*" => Http::response(['access_token' => 'token']),
        "{$host}/mpesa/stkpushquery/*" => Http::response(['ResultCode' => '0']),
    ]);

    app(MpesaService::class)->stkQuery('ws_CO_1');

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), "https://{$host}/mpesa/stkpushquery/v1/query"));
})->with([
    'sandbox' => ['sandbox', 'sandbox.safaricom.co.ke'],
    'production' => ['production', 'api.safaricom.co.ke'],
]);

test('caches access tokens separately per environment', function () {
    fakeMpesa();
    Cache::put('mpesa:production:access-token:c2b', 'production-token', 600);
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpushquery/*' => Http::response(['ResultCode' => '0'])]);

    app(MpesaService::class)->stkQuery('ws_CO_1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'stkpushquery') && $request->hasHeader('Authorization', 'Bearer test-token'));
    expect(Cache::get('mpesa:sandbox:access-token:c2b'))->toBe('test-token');
});

test('errors are logged without the STK password or phone numbers', function () {
    fakeMpesa();
    $mpesaLog = spyOnMpesaLog();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => Http::response(['errorCode' => '400.002.02', 'errorMessage' => 'Bad Request - Invalid PhoneNumber'], 400)]);

    expect(fn () => app(MpesaService::class)->stkPush(500, '254712345678', 'SportsBook', 'Deposit'))
        ->toThrow(MpesaApiException::class, 'Bad Request - Invalid PhoneNumber');

    $mpesaLog->shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
        $logged = json_encode($context);

        return $message === 'M-Pesa API error'
            && ! str_contains($logged, base64_encode('174379test-passkey'))
            && ! str_contains($logged, 'test-passkey')
            && ! str_contains($logged, '254712345678')
            && $context['payload']['Password'] === '[redacted]';
    });
});

test('a pending STK query surfaces Safaricom\'s still-processing error code', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpushquery/*' => Http::response(['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], 500)]);

    try {
        app(MpesaService::class)->stkQuery('ws_CO_1');
        $this->fail('Expected an MpesaApiException.');
    } catch (MpesaApiException $e) {
        expect($e->errorCode)->toBe('500.001.1001')
            ->and($e->statusCode)->toBe(500);
    }
});

test('B2C encrypts the initiator password with the certificate for the environment', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/b2c/*' => Http::response(['ResponseCode' => '0'])]);

    app(MpesaService::class)->b2c(['Amount' => 100, 'PartyB' => '254712345678']);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/b2c/')) {
            return false;
        }

        $encrypted = base64_decode((string) $request['SecurityCredential'], true);

        return $encrypted !== false
            && strlen($encrypted) === 256
            && $request['ResultURL'] === 'https://callbacks.sportsbook.test/api/mpesa/'.MPESA_TEST_SECRET.'/b2c/result'
            && $request['QueueTimeOutURL'] === 'https://callbacks.sportsbook.test/api/mpesa/'.MPESA_TEST_SECRET.'/b2c/timeout';
    });
});

test('status queries send real result and timeout URLs', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/transactionstatus/*' => Http::response(['ResponseCode' => '0'])]);

    app(MpesaService::class)->b2cTransactionStatus(['TransactionID' => 'TJK1A2B3C4']);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'transactionstatus')
        && $request['ResultURL'] === 'https://callbacks.sportsbook.test/api/mpesa/'.MPESA_TEST_SECRET.'/b2c/status/result');
});

test('refuses to build callback URLs without a long secret', function () {
    config(['mpesa.callback_secret' => 'short']);

    expect(fn () => MpesaService::callbackUrl('stk'))->toThrow(InvalidArgumentException::class);
});

test('C2B simulation refuses to run against production', function () {
    fakeMpesa();

    expect(fn () => (new MpesaService('production'))->c2bSimulate(100, '254708374149', '0712345678'))
        ->toThrow(InvalidArgumentException::class);
});
