<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super-admin';
    case Manager = 'manager';
    case Trader = 'trader';
    case Finance = 'finance';
    case Support = 'support';

    /**
     * Human-readable name shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Manager => __('Manager'),
            self::Trader => __('Trader / Risk'),
            self::Finance => __('Finance'),
            self::Support => __('Support'),
        };
    }

    /**
     * Options for admin panel select inputs, keyed by role name.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
