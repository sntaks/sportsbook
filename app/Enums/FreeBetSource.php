<?php

namespace App\Enums;

enum FreeBetSource: string
{
    /** Given by an admin from the player's page. */
    case Admin = 'admin';
    /** Given back because the bet it was used on ended void or was cancelled. */
    case Replacement = 'replacement';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Given by staff'),
            self::Replacement => __('Replacement'),
        };
    }
}
