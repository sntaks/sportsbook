<?php

namespace App\Enums;

/**
 * Deposit limits count rolling windows (decision 28): the last 24 hours, 7 days or 30 days, never calendar periods,
 * so depositing either side of midnight cannot double a limit.
 */
enum DepositLimitPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('Daily (last 24 hours)'),
            self::Weekly => __('Weekly (last 7 days)'),
            self::Monthly => __('Monthly (last 30 days)'),
        };
    }

    public function hours(): int
    {
        return match ($this) {
            self::Daily => 24,
            self::Weekly => 24 * 7,
            self::Monthly => 24 * 30,
        };
    }
}
