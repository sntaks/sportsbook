<?php

use App\Accounts\Sms\LogSmsSender;
use App\Accounts\Sms\TextSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.textsms.url' => 'https://textsms.test/api/services/sendsms/',
        'services.textsms.api_key' => 'test-key',
        'services.textsms.partner_id' => '1234',
        'services.textsms.shortcode' => 'SPORTSBOOK',
    ]);

    Http::preventStrayRequests();
});

test('posts the message to TextSMS and reports success', function () {
    Http::fake(['textsms.test/*' => Http::response(['responses' => [['response-code' => 200, 'response-description' => 'Success']]])]);

    expect(app(TextSmsSender::class)->send('0712 345 678', 'Your code is 123456'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://textsms.test/api/services/sendsms/'
        && $request->data() === [
            'apikey' => 'test-key',
            'partnerID' => '1234',
            'message' => 'Your code is 123456',
            'shortcode' => 'SPORTSBOOK',
            'mobile' => '254712345678',
        ]);
});

test('reads the misspelt response code TextSMS sometimes returns', function () {
    Http::fake(['textsms.test/*' => Http::response(['responses' => [['respose-code' => 200]]])]);

    expect(app(TextSmsSender::class)->send('254712345678', 'Hello'))->toBeTrue();
});

test('a rejected send is logged with the masked number and never the message', function () {
    Http::fake(['textsms.test/*' => Http::response(['responses' => [['response-code' => 1006, 'response-description' => 'Invalid credentials']]])]);
    Log::spy();

    expect(app(TextSmsSender::class)->send('254712345678', 'Your code is 123456'))->toBeFalse();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => $message === 'TextSMS send to 0712 *** 678: HTTP 200, code 1006 Invalid credentials');
});

test('sends nothing when TextSMS is not configured', function () {
    config(['services.textsms.api_key' => null]);
    Http::fake();

    expect(app(TextSmsSender::class)->send('254712345678', 'Hello'))->toBeFalse();

    Http::assertNothingSent();
});

test('the log sender refuses to run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    Log::spy();

    expect(app(LogSmsSender::class)->send('254712345678', 'Your code is 123456'))->toBeFalse();

    Log::shouldNotHaveReceived('info');
});
