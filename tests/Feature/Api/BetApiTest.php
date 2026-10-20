<?php

use App\Enums\BetStatus;
use App\Enums\UserStatus;
use App\Models\Bet;
use App\Models\User;
use App\Wallet\Ledger;

test('placing a bet returns 201 with the bet and takes the stake from the wallet', function () {
    $player = fundedPlayer(100_000);
    $fixture = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);

    $response = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [slipPick(selectionFor($fixture, 'home'))],
        'stake' => '100',
        'slip_key' => 'slip-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.stake_cents', 10_000)
        ->assertJsonPath('data.total_odds', '2.10')
        ->assertJsonPath('data.legs.0.fixture_name', 'Arsenal v Chelsea')
        ->assertJsonPath('data.legs.0.price', '2.10');
    expect(Bet::sole()->reference)->toBe($response->json('data.reference'))
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
});

test('sending the same slip again returns 200 with the bet already placed and charges once', function () {
    $player = fundedPlayer(100_000);
    $slip = ['picks' => [slipPick(selectionFor(pricedFixture()))], 'stake' => '100', 'slip_key' => 'slip-1'];
    $first = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), $slip)->assertCreated();

    $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), $slip)
        ->assertOk()
        ->assertJsonPath('data.reference', $first->json('data.reference'));

    expect(Bet::count())->toBe(1)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
});

test('a refused bet returns 422 with a reason the app can act on', function (Closure $makePlayer, Closure $makePick, string $reason, string $message) {
    $response = $this->actingAs($makePlayer(), 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [$makePick(pricedFixture())],
        'stake' => '100',
        'odds_change_policy' => 'none',
        'slip_key' => 'slip-1',
    ]);

    $response->assertUnprocessable()
        ->assertExactJson(['message' => $message, 'reason' => $reason]);
    expect(Bet::count())->toBe(0);
})->with([
    'not enough money' => [fn () => fundedPlayer(5_000), fn ($fixture) => slipPick(selectionFor($fixture)), 'insufficient-funds', 'Your balance is KES 50.00. Deposit to place this bet.'],
    'price moved' => [fn () => fundedPlayer(), fn ($fixture) => slipPick(selectionFor($fixture), '1.90'), 'price-changed', 'Some odds have changed. Check the new odds, then place your bet.'],
    'suspended account' => [fn () => fundedPlayer(100_000, ['status' => UserStatus::Suspended]), fn ($fixture) => slipPick(selectionFor($fixture)), 'not-allowed', 'Your account is suspended. Please contact support.'],
]);

test('placing a bet without a slip key returns 422', function () {
    $this->actingAs(fundedPlayer(), 'sanctum')
        ->postJson(route('api.v1.bets.store'), ['picks' => [slipPick(selectionFor(pricedFixture()))], 'stake' => '100'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slip_key' => 'The slip key field is required.']);

    expect(Bet::count())->toBe(0);
});

test('my bets return 200 with only my own bets, filtered by open or settled', function () {
    $player = User::factory()->create();
    $open = Bet::factory()->for($player)->create();
    $won = Bet::factory()->for($player)->create(['status' => BetStatus::Won]);
    Bet::factory()->create();

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.bets.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.bets.index', ['status' => 'open']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference', $open->reference);
    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.bets.index', ['status' => 'settled']))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference', $won->reference);
});

test('a bet returns 200 by its reference', function () {
    $player = User::factory()->create();
    $bet = Bet::factory()->for($player)->create();

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.bets.show', $bet->reference))
        ->assertOk()
        ->assertJsonPath('data.reference', $bet->reference);
});

test('another player\'s bet returns 404', function () {
    $bet = Bet::factory()->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson(route('api.v1.bets.show', $bet->reference))
        ->assertNotFound();
});

test('bets return 401 without a token', function () {
    $this->getJson(route('api.v1.bets.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.bets.store'), [])->assertUnauthorized();
});
