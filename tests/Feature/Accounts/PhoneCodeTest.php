<?php

use App\Accounts\PhoneCode;
use App\Enums\PhoneCodePurpose;
use App\Enums\PhoneCodeResult;

test('texts a six-digit code that verifies once', function () {
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);

    expect($phoneCode->send('0712 345 678', PhoneCodePurpose::Registration))->toBe(PhoneCodeResult::Sent);

    $code = $sms->lastCodeFor('254712345678');

    expect($sms->sent[0]['message'])->toBe(config('app.name')." code: {$code}. Valid 10 min. Never share it.")
        ->and($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $code))->toBe(PhoneCodeResult::Verified)
        ->and($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $code))->toBe(PhoneCodeResult::Expired);
});

test('a code only works for the number and purpose it was sent for', function () {
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);
    $phoneCode->send('254712345678', PhoneCodePurpose::Registration);
    $code = $sms->lastCodeFor('254712345678');

    expect($phoneCode->verify('254712345678', PhoneCodePurpose::PasswordReset, $code))->toBe(PhoneCodeResult::Expired)
        ->and($phoneCode->verify('254712345679', PhoneCodePurpose::Registration, $code))->toBe(PhoneCodeResult::Expired)
        ->and($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $code))->toBe(PhoneCodeResult::Verified);
});

test('a code expires after ten minutes', function () {
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);
    $phoneCode->send('254712345678', PhoneCodePurpose::Registration);

    $this->travel(11)->minutes();

    expect($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $sms->lastCodeFor('254712345678')))->toBe(PhoneCodeResult::Expired);
});

test('five wrong codes use the code up', function () {
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);
    $phoneCode->send('254712345678', PhoneCodePurpose::Registration);
    $code = $sms->lastCodeFor('254712345678');
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $attempt) {
        expect($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $wrong))->toBe(PhoneCodeResult::Invalid);
    }

    expect($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $wrong))->toBe(PhoneCodeResult::TooMany)
        ->and($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $code))->toBe(PhoneCodeResult::Expired);
});

test('waits a minute between codes to the same number', function () {
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);
    $phoneCode->send('254712345678', PhoneCodePurpose::Registration);

    expect($phoneCode->send('254712345678', PhoneCodePurpose::Registration))->toBe(PhoneCodeResult::Cooldown)
        ->and($phoneCode->secondsUntilResend('254712345678', PhoneCodePurpose::Registration))->toBe(60);

    $this->travel(61)->seconds();

    expect($phoneCode->send('254712345678', PhoneCodePurpose::Registration))->toBe(PhoneCodeResult::Sent)
        ->and($sms->sent)->toHaveCount(2);
});

test('caps codes per number per hour, across purposes', function () {
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);

    foreach (range(1, 5) as $send) {
        $phoneCode->send('254712345678', $send % 2 === 0 ? PhoneCodePurpose::PasswordReset : PhoneCodePurpose::Registration);
        $this->travel(61)->seconds();
    }

    expect($phoneCode->send('254712345678', PhoneCodePurpose::PhoneChange))->toBe(PhoneCodeResult::Limited)
        ->and($sms->sent)->toHaveCount(5);
});

test('caps codes per IP address per hour, across numbers', function () {
    config(['sportsbook.phone_codes.sends_per_hour_ip' => 2]);
    $sms = fakeSms();
    $phoneCode = app(PhoneCode::class);

    $phoneCode->send('254711111111', PhoneCodePurpose::Registration, '203.0.113.9');
    $phoneCode->send('254722222222', PhoneCodePurpose::Registration, '203.0.113.9');

    expect($phoneCode->send('254733333333', PhoneCodePurpose::Registration, '203.0.113.9'))->toBe(PhoneCodeResult::Limited)
        ->and($phoneCode->send('254733333333', PhoneCodePurpose::Registration, '198.51.100.4'))->toBe(PhoneCodeResult::Sent)
        ->and($sms->sent)->toHaveCount(3);
});

test('a failed SMS still counts towards the limits and leaves no code to verify', function () {
    $sms = fakeSms(accepts: false);
    $phoneCode = app(PhoneCode::class);

    expect($phoneCode->send('254712345678', PhoneCodePurpose::Registration))->toBe(PhoneCodeResult::Failed)
        ->and($phoneCode->send('254712345678', PhoneCodePurpose::Registration))->toBe(PhoneCodeResult::Cooldown)
        ->and($phoneCode->verify('254712345678', PhoneCodePurpose::Registration, $sms->lastCodeFor('254712345678')))->toBe(PhoneCodeResult::Expired);
});

test('does not text a number that is not a Kenyan mobile', function () {
    $sms = fakeSms();

    expect(app(PhoneCode::class)->send('0201234567', PhoneCodePurpose::Registration))->toBe(PhoneCodeResult::Failed)
        ->and($sms->sent)->toBeEmpty();
});
