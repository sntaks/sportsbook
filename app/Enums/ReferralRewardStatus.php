<?php

namespace App\Enums;

/**
 * What happened when an invited player qualified, from the referrer's side.
 */
enum ReferralRewardStatus: string
{
    /** The referrer got the free bet. */
    case Rewarded = 'rewarded';
    /** The referrer already had the month's maximum. */
    case Capped = 'capped';
    /** The referrer was suspended or on a break from betting at the time. */
    case Ineligible = 'ineligible';

    public function label(): string
    {
        return match ($this) {
            self::Rewarded => __('Free bet given'),
            self::Capped => __('Over the monthly limit'),
            self::Ineligible => __('Not given: betting was paused on your account'),
        };
    }
}
