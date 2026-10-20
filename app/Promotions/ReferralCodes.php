<?php

namespace App\Promotions;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * Players' invite codes. A code is made the first time the player looks at it, so players who never invite anyone
 * never get one.
 */
class ReferralCodes
{
    /** No 0/O, 1/I/L: codes are read out and typed in by hand. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 8;

    public static function enabled(): bool
    {
        return (bool) config('sportsbook.promotions.referrals.enabled');
    }

    /**
     * A code as typed, in the form it is stored: trimmed and upper case; null when blank.
     */
    public static function normalize(mixed $code): ?string
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        return $code === '' ? null : $code;
    }

    /**
     * The player's code, made now if they have none. Safe to call from two requests at once: both get the code that
     * was stored.
     */
    public function codeFor(User $player): string
    {
        if ($player->referral_code !== null) {
            return $player->referral_code;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                User::query()->whereKey($player->id)->whereNull('referral_code')->update(['referral_code' => $this->generate()]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $player->referral_code = (string) User::query()->whereKey($player->id)->value('referral_code');
            $player->syncOriginalAttribute('referral_code');

            return $player->referral_code;
        }

        throw new RuntimeException('Could not generate a unique referral code.');
    }

    /**
     * The sign-up link with the player's code in it.
     */
    public function linkFor(User $player): string
    {
        return route('register', ['ref' => $this->codeFor($player)]);
    }

    private function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
