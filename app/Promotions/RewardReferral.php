<?php

namespace App\Promotions;

use App\Enums\BetStatus;
use App\Enums\JackpotEntryStatus;
use App\Enums\MpesaDepositStatus;
use App\Enums\ReferralRewardStatus;
use App\Models\ReferralReward;
use App\Models\User;
use App\Support\Money;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Rewards the referrer once the player they invited qualifies (decisions 44 and 49): within the window after signing
 * up, the player has deposited at least min_deposit and staked at least min_stake in cash. Cash stakes are bets not
 * paid with a free bet whose stake was not given back (not cancelled or void), plus jackpot entries not refunded.
 * Deposits paid from the referrer's own number do not count.
 *
 * The outcome is decided once per invited player and never changes afterwards, even if a bet that counted is later
 * cancelled: a free bet, over the monthly limit, or not given because the referrer could not bet at the time (a
 * player on a break from betting is not sent offers). Both players' rows are locked while deciding, and
 * referral_rewards.referred_id is unique, so two checks at once cannot reward twice or pass the monthly limit.
 */
class RewardReferral
{
    public function __construct(private GrantFreeBet $grantFreeBet) {}

    /**
     * Whether checking the player could reward anyone. Needs no queries, so callers can ask on every deposit and bet.
     */
    public static function mightApply(User $player): bool
    {
        return ReferralCodes::enabled()
            && $player->referred_by_id !== null
            && $player->created_at !== null
            && $player->created_at->gt(now()->subDays((int) config('sportsbook.promotions.referrals.window_days')));
    }

    /**
     * Decide the reward if the player now qualifies. Returns null when they do not yet, or it was already decided.
     */
    public function handle(User $player): ?ReferralReward
    {
        if (! self::mightApply($player)) {
            return null;
        }

        return DB::transaction(function () use ($player): ?ReferralReward {
            $player = User::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();

            if ($player->referralOutcome()->exists()) {
                return null;
            }

            $referrer = User::query()->whereKey($player->referred_by_id)->lockForUpdate()->first();

            if ($referrer === null) {
                return null;
            }

            $deadline = $player->created_at->addDays((int) config('sportsbook.promotions.referrals.window_days'));
            $deposited = $this->depositedCents($player, $referrer, $deadline);
            $staked = $this->stakedCents($player, $deadline);

            if ($deposited < Money::fromShillings((int) config('sportsbook.promotions.referrals.min_deposit'))
                || $staked < Money::fromShillings((int) config('sportsbook.promotions.referrals.min_stake'))) {
                return null;
            }

            $status = match (true) {
                ! $referrer->canBet() => ReferralRewardStatus::Ineligible,
                $this->rewardsThisMonth($referrer) >= (int) config('sportsbook.promotions.referrals.monthly_cap') => ReferralRewardStatus::Capped,
                default => ReferralRewardStatus::Rewarded,
            };

            $reward = ReferralReward::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $player->id,
                'status' => $status,
                'free_bet_id' => $status === ReferralRewardStatus::Rewarded ? $this->grantFreeBet->forReferral($referrer, $player)->id : null,
            ]);

            activity('promotions')
                ->performedOn($referrer)
                ->event('referral-reward')
                ->withProperties([
                    'status' => $status->value,
                    'referred_id' => $player->id,
                    'referred_phone' => PhoneNumber::mask($player->phone),
                    'free_bet_id' => $reward->free_bet_id,
                    'deposited' => Money::format($deposited),
                    'staked' => Money::format($staked),
                ])
                ->log('Referral reward decided');

            return $reward;
        });
    }

    /**
     * Rewards the referrer has been given this calendar month, Kenya time.
     */
    public function rewardsThisMonth(User $referrer): int
    {
        return $referrer->referralRewards()
            ->where('status', ReferralRewardStatus::Rewarded)
            ->where('created_at', '>=', now()->timezone(config('sportsbook.timezone'))->startOfMonth()->setTimezone(config('app.timezone')))
            ->count();
    }

    /**
     * Completed deposits before the deadline, leaving out any paid from the referrer's number. That check only bites
     * where M-Pesa gives the payer's number in full: an STK push always goes to the player's own number, and Paybill
     * numbers arrive masked.
     */
    private function depositedCents(User $player, User $referrer, CarbonInterface $deadline): int
    {
        return (int) $player->mpesaDeposits()
            ->where('status', MpesaDepositStatus::Completed)
            ->where('completed_at', '<=', $deadline)
            ->where('phone', '!=', (string) $referrer->phone)
            ->sum(DB::raw('COALESCE(paid_amount, amount)'));
    }

    private function stakedCents(User $player, CarbonInterface $deadline): int
    {
        $bets = (int) $player->bets()
            ->whereNull('free_bet_id')
            ->whereNotIn('status', [BetStatus::Cancelled, BetStatus::Void])
            ->where('placed_at', '<=', $deadline)
            ->sum('stake');

        $entries = (int) $player->jackpotEntries()
            ->where('status', '!=', JackpotEntryStatus::Refunded)
            ->where('placed_at', '<=', $deadline)
            ->sum('stake');

        return $bets + $entries;
    }
}
