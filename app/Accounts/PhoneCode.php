<?php

namespace App\Accounts;

use App\Accounts\Sms\SmsSender;
use App\Enums\PhoneCodePurpose;
use App\Enums\PhoneCodeResult;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SMS codes that prove someone holds a phone number. Built from Kadi's PhoneOtp, but keyed by the
 * number and a purpose instead of a user, so it also works for people who don't have an account yet.
 *
 * The code is kept only as an HMAC in the cache for `sportsbook.phone_codes.ttl_minutes`. A few wrong
 * tries burn it. Sends cost money, so they are capped per number and per IP (SMS pumping). Codes are
 * never logged.
 */
class PhoneCode
{
    public function __construct(private SmsSender $sms) {}

    public static function message(string $code): string
    {
        return __(':brand code: :code. Valid :minutes min. Never share it.', [
            'brand' => config('app.name'),
            'code' => $code,
            'minutes' => config('sportsbook.phone_codes.ttl_minutes'),
        ]);
    }

    /**
     * Send a new code. Returns Sent, Cooldown, Limited or Failed.
     */
    public function send(string $phone, PhoneCodePurpose $purpose, ?string $ip = null): PhoneCodeResult
    {
        $phone = PhoneNumber::normalize($phone);

        if ($phone === null || ! PhoneNumber::isValid($phone)) {
            return PhoneCodeResult::Failed;
        }

        if ($this->secondsUntilResend($phone, $purpose) > 0) {
            return PhoneCodeResult::Cooldown;
        }

        $limits = ['phone-code:phone:'.$phone => (int) config('sportsbook.phone_codes.sends_per_hour_phone')];

        if ($ip !== null) {
            $limits['phone-code:ip:'.$ip] = (int) config('sportsbook.phone_codes.sends_per_hour_ip');
        }

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return PhoneCodeResult::Limited;
            }
        }

        $length = (int) config('sportsbook.phone_codes.length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        // Counted before sending: a failed or slow send still costs a slot, so it cannot be hammered.
        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 3600);
        }
        RateLimiter::hit($this->resendKey($phone, $purpose), (int) config('sportsbook.phone_codes.resend_seconds'));

        if (! $this->sms->send($phone, self::message($code))) {
            return PhoneCodeResult::Failed;
        }

        Cache::put($this->codeKey($phone, $purpose), [
            'hash' => $this->hash($phone, $purpose, $code),
            'attempts' => 0,
        ], now()->addMinutes((int) config('sportsbook.phone_codes.ttl_minutes')));

        return PhoneCodeResult::Sent;
    }

    /**
     * Check a code. A correct code is used up. Returns Verified, Invalid, Expired or TooMany.
     */
    public function verify(string $phone, PhoneCodePurpose $purpose, string $code): PhoneCodeResult
    {
        $phone = (string) PhoneNumber::normalize($phone);
        $key = $this->codeKey($phone, $purpose);
        $pending = Cache::get($key);

        if (! is_array($pending)) {
            return PhoneCodeResult::Expired;
        }

        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (! hash_equals($pending['hash'], $this->hash($phone, $purpose, $code))) {
            $pending['attempts']++;

            if ($pending['attempts'] >= (int) config('sportsbook.phone_codes.max_attempts')) {
                Cache::forget($key);

                return PhoneCodeResult::TooMany;
            }

            Cache::put($key, $pending, now()->addMinutes((int) config('sportsbook.phone_codes.ttl_minutes')));

            return PhoneCodeResult::Invalid;
        }

        Cache::forget($key);

        return PhoneCodeResult::Verified;
    }

    public function secondsUntilResend(string $phone, PhoneCodePurpose $purpose): int
    {
        $key = $this->resendKey((string) PhoneNumber::normalize($phone), $purpose);

        return RateLimiter::tooManyAttempts($key, 1) ? RateLimiter::availableIn($key) : 0;
    }

    private function hash(string $phone, PhoneCodePurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$purpose->value}|{$phone}|{$code}", (string) config('app.key'));
    }

    private function codeKey(string $phone, PhoneCodePurpose $purpose): string
    {
        return "phone-code:code:{$purpose->value}:{$phone}";
    }

    private function resendKey(string $phone, PhoneCodePurpose $purpose): string
    {
        return "phone-code:resend:{$purpose->value}:{$phone}";
    }
}
