<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case Deposit = 'deposit';
    case DepositTax = 'deposit-tax';
    case Adjustment = 'adjustment';

    /**
     * Name shown to the player in their transaction history.
     */
    public function label(): string
    {
        return match ($this) {
            self::Deposit => __('Deposit'),
            self::DepositTax => __('Tax on deposit'),
            self::Adjustment => __('Adjustment'),
        };
    }
}
