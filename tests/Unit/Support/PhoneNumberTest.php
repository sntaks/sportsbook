<?php

use App\Support\PhoneNumber;

test('stores every common way of writing a Kenyan mobile number the same way', function (string $written) {
    expect(PhoneNumber::normalize($written))->toBe('254712345678')
        ->and(PhoneNumber::isValid($written))->toBeTrue();
})->with([
    'local' => '0712345678',
    'local with spaces' => '0712 345 678',
    'international' => '+254712345678',
    'international with dashes' => '+254-712-345-678',
    'country code' => '254712345678',
    'without the leading zero' => '712345678',
]);

test('accepts 01XX numbers', function () {
    expect(PhoneNumber::normalize('0110 123 456'))->toBe('254110123456')
        ->and(PhoneNumber::isValid('0110123456'))->toBeTrue();
});

test('rejects numbers that are not Kenyan mobiles', function (?string $written) {
    expect(PhoneNumber::isValid($written))->toBeFalse();
})->with([
    'landline' => '0201234567',
    'too short' => '071234567',
    'too long' => '07123456789',
    'other country' => '+447700900123',
    'blank' => '',
    'null' => null,
]);

test('blank numbers normalize to null, never an empty string', function () {
    expect(PhoneNumber::normalize(' - '))->toBeNull();
});

test('masks a number for display', function () {
    expect(PhoneNumber::mask('254712345678'))->toBe('0712 *** 678')
        ->and(PhoneNumber::mask(null))->toBe('****');
});
