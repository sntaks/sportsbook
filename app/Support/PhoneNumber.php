<?php

namespace App\Support;

/**
 * The one place that decides what a stored phone number looks like: 254 followed by 9 digits
 * (254712345678), with no `+`, spaces or leading 0. `users.phone` and every M-Pesa request use it.
 *
 * Kenyan mobile numbers only (Safaricom, Airtel and Telkom; 07XX and 01XX). Widen FORMAT and
 * normalize() together if other countries are ever allowed.
 */
class PhoneNumber
{
    public const FORMAT = '/^254[17]\d{8}$/';

    /**
     * Strip separators and map +254…, 254…, 0… and bare 7…/1… numbers to 254XXXXXXXXX. Anything that
     * cannot be mapped is returned as its cleaned digits so validation can reject it. Blank is null,
     * never '' (an empty string would collide with itself under a unique index).
     */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '254')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        if (preg_match('/^[17]\d{8}$/', $digits)) {
            return '254'.$digits;
        }

        return $digits;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        return $normalized !== null && preg_match(self::FORMAT, $normalized) === 1;
    }

    /**
     * 254712345678 -> 0712 *** 678, for showing a number back to its owner or in logs.
     */
    public static function mask(?string $value): string
    {
        $normalized = (string) self::normalize($value);

        return strlen($normalized) === 12
            ? '0'.substr($normalized, 3, 3).' *** '.substr($normalized, -3)
            : '****';
    }
}
