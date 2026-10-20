<?php

use App\Enums\ReferralRewardStatus;
use App\Models\Promotion;
use App\Models\ReferralReward;
use App\Models\User;

test('promotions return 200 with the running ones and no body', function () {
    $running = Promotion::factory()->create(['slug' => 'multibet-bonus', 'ends_at' => now()->addWeek()]);
    Promotion::factory()->draft()->create();

    $this->getJson(route('api.v1.promotions.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'multibet-bonus')
        ->assertJsonPath('data.0.title', $running->title)
        ->assertJsonPath('data.0.ends_at', $running->ends_at->toIso8601String())
        ->assertJsonPath('data.0.url', route('promotions.show', $running))
        ->assertJsonMissingPath('data.0.body_html');
});

test('a promotion returns 200 with its body as Markdown and safe HTML', function () {
    Promotion::factory()->create(['slug' => 'jackpot', 'body' => "## Prizes\n\n<script>x</script>"]);

    $this->getJson(route('api.v1.promotions.show', 'jackpot'))
        ->assertOk()
        ->assertJsonPath('data.body_markdown', "## Prizes\n\n<script>x</script>")
        ->assertJsonPath('data.body_html', "<h2>Prizes</h2>\n\n");
});

test('a promotion that is not running returns 404', function () {
    Promotion::factory()->ended()->create(['slug' => 'old']);

    $this->getJson(route('api.v1.promotions.show', 'old'))->assertNotFound();
    $this->getJson(route('api.v1.promotions.show', 'missing'))->assertNotFound();
});

test('my invites return 200 with my code, link, rewards this month and my friends only', function () {
    $player = User::factory()->create(['referral_code' => 'ABCD2345']);
    $rewarded = User::factory()->create(['referred_by_id' => $player->id, 'phone' => '254711000001']);
    ReferralReward::factory()->create(['referrer_id' => $player->id, 'referred_id' => $rewarded->id]);
    User::factory()->create(['referred_by_id' => $player->id, 'phone' => '254711000002']);
    User::factory()->create(['referred_by_id' => User::factory()->create()->id, 'phone' => '254711000003']);

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.me.referral'))
        ->assertOk()
        ->assertJsonPath('code', 'ABCD2345')
        ->assertJsonPath('link', route('register', ['ref' => 'ABCD2345']))
        ->assertJsonPath('rewards_this_month', 1)
        ->assertJsonPath('monthly_cap', 10)
        ->assertJsonPath('invited_count', 2)
        ->assertJsonCount(2, 'friends')
        ->assertJsonPath('friends.0.phone', '0711 *** 002')
        ->assertJsonPath('friends.0.status', 'waiting')
        ->assertJsonPath('friends.1.status', ReferralRewardStatus::Rewarded->value);
});

test('my invites make a code the first time', function () {
    $player = User::factory()->create();

    $code = $this->actingAs($player, 'sanctum')->getJson(route('api.v1.me.referral'))->assertOk()->json('code');

    expect($code)->toMatch('/^[A-HJ-NP-Z2-9]{8}$/')
        ->and($player->fresh()->referral_code)->toBe($code);
});

test('my invites return 401 without a token', function () {
    $this->getJson(route('api.v1.me.referral'))->assertUnauthorized();
});

test('my invites return 404 while invites are switched off', function () {
    config(['sportsbook.promotions.referrals.enabled' => false]);

    $this->actingAs(User::factory()->create(), 'sanctum')->getJson(route('api.v1.me.referral'))->assertNotFound();
});
