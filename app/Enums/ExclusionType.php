<?php

namespace App\Enums;

/**
 * A break a player takes from betting. While it lasts they can log in and withdraw, but not deposit or bet.
 * It can be extended but never shortened.
 */
enum ExclusionType: string
{
    case CoolOff = 'cool-off';
    case SelfExclusion = 'self-exclusion';

    public function label(): string
    {
        return match ($this) {
            self::CoolOff => __('Cool-off'),
            self::SelfExclusion => __('Self-exclusion'),
        };
    }

    /**
     * The lengths a player can choose, keyed by a short code, as [label, ISO 8601 duration].
     *
     * @return array<string, array{string, string}>
     */
    public function durations(): array
    {
        return match ($this) {
            self::CoolOff => [
                '24h' => [__('24 hours'), 'PT24H'],
                '7d' => [__('7 days'), 'P7D'],
                '30d' => [__('30 days'), 'P30D'],
            ],
            self::SelfExclusion => [
                '6m' => [__('6 months'), 'P6M'],
                '1y' => [__('1 year'), 'P1Y'],
                '5y' => [__('5 years'), 'P5Y'],
            ],
        };
    }
}
