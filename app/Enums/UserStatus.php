<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    /** Stopped by an admin: can log in and see the wallet, but cannot deposit, withdraw or bet. */
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
        };
    }
}
