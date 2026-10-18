<?php

namespace App\Wallet\Deposits;

use App\Models\User;
use App\Support\PhoneNumber;

/**
 * Paybill account numbers are the player's phone number, typed however they like (0712…, 254712…).
 * Safaricom masks the payer's own number in Paybill callbacks, so the account number is the only thing that
 * links a payment to a player.
 */
class PaybillAccount
{
    public static function player(mixed $accountNumber): ?User
    {
        $phone = PhoneNumber::normalize(is_scalar($accountNumber) ? (string) $accountNumber : null);

        return PhoneNumber::isValid($phone)
            ? User::query()->where('phone', $phone)->first()
            : null;
    }
}
