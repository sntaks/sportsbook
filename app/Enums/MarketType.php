<?php

namespace App\Enums;

/**
 * Market types, keyed by their The Odds API market key.
 */
enum MarketType: string
{
    case MatchResult = 'h2h';
    case Totals = 'totals';

    public function label(): string
    {
        return match ($this) {
            self::MatchResult => __('1X2'),
            self::Totals => __('Over/Under'),
        };
    }
}
