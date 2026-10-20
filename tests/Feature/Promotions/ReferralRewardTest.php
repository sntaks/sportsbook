<?php

use App\Enums\BetStatus;
use App\Enums\FreeBetSource;
use App\Enums\FreeBetStatus;
use App\Enums\JackpotEntryStatus;
use App\Enums\MpesaDepositStatus;
use App\Enums\ReferralRewardStatus;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Models\JackpotEntry;
use App\Models\MpesaDeposit;
use App\Models\ReferralReward;
use App\Models\User;
use App\Promotions\RewardReferral;
use App\Wallet\Deposits\CompleteDeposit;
use App\Wallet\Ledger;
use Spatie\Activitylog\Models\Activity;

/**
 * A player who signed up with the referrer's code, with KES 1,000 credited outside M-Pesa so they can bet (that credit
 * is not a deposit and does not count).
 */
function invitedBy(User $referrer, array $attributes = []): User
{
    $player = User::factory()->create(['referred_by_id' => $referrer->id, ...$attributes]);
    app(Ledger::class)->post($player, WalletTransactionType::Deposit, 100_000, "test-funding:{$player->id}");

    return $player;
}

/**
 * A completed M-Pesa deposit, credited the way a real one is.
 */
function referralDeposit(User $player, int $cents, ?string $phone = null): MpesaDeposit
{
    $deposit = MpesaDeposit::factory()->for($player)->create(['amount' => $cents, 'phone' => $phone ?? $player->phone]);

    return app(CompleteDeposit::class)->handle($deposit, 'TJK'.$deposit->id);
}

test('the referrer gets a KES 100 free bet once their friend has deposited KES 100 and staked KES 200', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);

    referralDeposit($friend, 10_000);
    betOn($friend, [[pricedFixture(), 'home']], '150');

    expect($referrer->freeBets()->exists())->toBeFalse();

    betOn($friend, [[pricedFixture(), 'home']], '50');

    $reward = ReferralReward::sole();
    expect($reward)
        ->referrer_id->toBe($referrer->id)
        ->referred_id->toBe($friend->id)
        ->status->toBe(ReferralRewardStatus::Rewarded)
        ->and($reward->freeBet)
        ->user_id->toBe($referrer->id)
        ->amount->toBe(10_000)
        ->min_odds->toBe('1.50')
        ->source->toBe(FreeBetSource::Referral)
        ->status->toBe(FreeBetStatus::Available)
        ->and($reward->freeBet->expires_at->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and(Activity::query()->where('event', 'referral-reward')->sole()->subject->is($referrer))->toBeTrue();
});

test('staking without an M-Pesa deposit earns nothing', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);

    betOn($friend, [[pricedFixture(), 'home']], '500');

    expect(ReferralReward::query()->exists())->toBeFalse()
        ->and($referrer->freeBets()->exists())->toBeFalse();
});

test('free bets and bets whose stake went back do not count towards the stake', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);
    Bet::factory()->for($friend)->create(['stake' => 20_000, 'status' => BetStatus::Cancelled]);
    Bet::factory()->for($friend)->create(['stake' => 20_000, 'status' => BetStatus::Void]);
    Bet::factory()->for($friend)->create(['stake' => 20_000, 'free_bet_id' => freeBetFor($friend)->id]);

    referralDeposit($friend, 10_000);

    expect(ReferralReward::query()->exists())->toBeFalse();

    Bet::factory()->for($friend)->create(['stake' => 20_000, 'status' => BetStatus::Lost]);
    app(RewardReferral::class)->handle($friend);

    expect(ReferralReward::sole()->status)->toBe(ReferralRewardStatus::Rewarded);
});

test('jackpot entries count towards the stake, refunded ones do not', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);
    JackpotEntry::factory()->for($friend)->create(['stake' => 20_000, 'status' => JackpotEntryStatus::Refunded]);
    referralDeposit($friend, 10_000);

    expect(ReferralReward::query()->exists())->toBeFalse();

    enterJackpot($friend, openJackpot(), ['1', 'X', '2']);
    enterJackpot($friend, openJackpot(), ['1', 'X', '2']);
    enterJackpot($friend, openJackpot(), ['1', 'X', '2']);

    expect(ReferralReward::sole()->status)->toBe(ReferralRewardStatus::Rewarded);
});

test('a friend who qualifies more than 30 days after signing up earns nothing', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);
    referralDeposit($friend, 10_000);

    $this->travel(31)->days();
    betOn($friend, [[pricedFixture(), 'home']], '200');
    app(RewardReferral::class)->handle($friend);

    expect(ReferralReward::query()->exists())->toBeFalse();
});

test('a deposit paid from the referrer\'s own number does not count', function () {
    $referrer = User::factory()->create(['phone' => '254700000009']);
    $friend = invitedBy($referrer);

    referralDeposit($friend, 10_000, phone: '254700000009');
    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::query()->exists())->toBeFalse();
});

test('past 10 rewards in a calendar month a friend is recorded as over the limit, with no free bet; next month counts afresh', function () {
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 15)->setTime(12, 0));
    $referrer = User::factory()->create();
    ReferralReward::factory()->count(10)->create(['referrer_id' => $referrer->id]);
    $friend = invitedBy($referrer);

    referralDeposit($friend, 10_000);
    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::query()->where('referred_id', $friend->id)->sole())
        ->status->toBe(ReferralRewardStatus::Capped)
        ->free_bet_id->toBeNull()
        ->and($referrer->freeBets()->exists())->toBeFalse();

    // 00:30 on 1 November in Nairobi is still 31 October in UTC.
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 11, 1)->setTime(0, 30));
    $next = invitedBy($referrer);
    referralDeposit($next, 10_000);
    betOn($next, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::query()->where('referred_id', $next->id)->sole()->status)->toBe(ReferralRewardStatus::Rewarded);
});

test('a referrer on a break from betting is not rewarded for that friend', function () {
    $referrer = User::factory()->create(['excluded_until' => now()->addMonth()]);
    $friend = invitedBy($referrer);

    referralDeposit($friend, 10_000);
    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::sole())->status->toBe(ReferralRewardStatus::Ineligible)->free_bet_id->toBeNull()
        ->and($referrer->freeBets()->exists())->toBeFalse();
});

test('checking a friend again never rewards twice', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);
    referralDeposit($friend, 10_000);
    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(app(RewardReferral::class)->handle($friend))->toBeNull();
    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::count())->toBe(1)
        ->and($referrer->freeBets()->count())->toBe(1);
});

test('with invites switched off nobody is rewarded', function () {
    config(['sportsbook.promotions.referrals.enabled' => false]);
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);

    referralDeposit($friend, 10_000);
    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::query()->exists())->toBeFalse();
});

test('a deposit that is not completed does not count', function () {
    $referrer = User::factory()->create();
    $friend = invitedBy($referrer);
    MpesaDeposit::factory()->for($friend)->create(['amount' => 10_000, 'status' => MpesaDepositStatus::Failed]);

    betOn($friend, [[pricedFixture(), 'home']], '200');

    expect(ReferralReward::query()->exists())->toBeFalse();
});
