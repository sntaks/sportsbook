<?php

namespace App\Promotions;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a player sees about their invites: their code and link, the month's rewards against the limit, and how each
 * friend they invited is getting on. Shared by the wallet page and the API.
 */
class ReferralSummary
{
    /** How many invited friends are listed, newest first. */
    private const FRIENDS_SHOWN = 20;

    /**
     * @param  Collection<int, User>  $friends  With referralOutcome loaded.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $link,
        public readonly int $rewardsThisMonth,
        public readonly int $monthlyCap,
        public readonly int $invitedCount,
        public readonly Collection $friends,
    ) {}

    public static function for(User $player): self
    {
        $codes = app(ReferralCodes::class);

        return new self(
            code: $codes->codeFor($player),
            link: $codes->linkFor($player),
            rewardsThisMonth: app(RewardReferral::class)->rewardsThisMonth($player),
            monthlyCap: (int) config('sportsbook.promotions.referrals.monthly_cap'),
            invitedCount: $player->referrals()->count(),
            friends: $player->referrals()->with('referralOutcome')->latest('id')->limit(self::FRIENDS_SHOWN)->get(),
        );
    }

    /**
     * Waiting (still inside the window), expired (did not qualify in time), or the decided outcome.
     *
     * @return 'waiting'|'expired'|'rewarded'|'capped'|'ineligible'
     */
    public static function friendStatus(User $friend): string
    {
        if ($friend->referralOutcome !== null) {
            return $friend->referralOutcome->status->value;
        }

        return self::deadlineFor($friend)->isFuture() ? 'waiting' : 'expired';
    }

    public static function friendStatusLabel(User $friend): string
    {
        return match (self::friendStatus($friend)) {
            'waiting' => __('Waiting for their deposit and bets, until :date', ['date' => self::deadlineFor($friend)->timezone(config('sportsbook.timezone'))->format('j M')]),
            'expired' => __('Didn\'t qualify within :days days', ['days' => config('sportsbook.promotions.referrals.window_days')]),
            default => $friend->referralOutcome->status->label(),
        };
    }

    /**
     * When the friend's window to qualify closes.
     */
    public static function deadlineFor(User $friend): CarbonInterface
    {
        return $friend->created_at->addDays((int) config('sportsbook.promotions.referrals.window_days'));
    }

    /**
     * The rules in plain words.
     */
    public static function terms(): string
    {
        return __('Share your link. When a friend signs up with it, deposits KES :deposit and stakes KES :stake of their own money within :days days, you get a KES :reward free bet (odds :odds+, use within :expiry days). Up to :cap rewards a month.', [
            'deposit' => number_format((int) config('sportsbook.promotions.referrals.min_deposit')),
            'stake' => number_format((int) config('sportsbook.promotions.referrals.min_stake')),
            'days' => config('sportsbook.promotions.referrals.window_days'),
            'reward' => number_format((int) config('sportsbook.promotions.referrals.reward.amount')),
            'odds' => config('sportsbook.promotions.referrals.reward.min_odds'),
            'expiry' => config('sportsbook.promotions.referrals.reward.expiry_days'),
            'cap' => config('sportsbook.promotions.referrals.monthly_cap'),
        ]);
    }
}
