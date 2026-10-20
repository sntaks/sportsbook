<?php

use App\Enums\FreeBetStatus;
use App\Models\Bet;
use App\Models\User;
use App\Wallet\Ledger;

test('my free bets return 200 with only my own, newest first, with their status', function () {
    $player = User::factory()->create();
    $expired = freeBetFor($player, ['expires_at' => now()->subDay()]);
    $available = freeBetFor($player, ['amount' => 25_000, 'min_odds' => '2.00']);
    freeBetFor(User::factory()->create());

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.free-bets.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('data.0.id', $available->id)
        ->assertJsonPath('data.0.amount_cents', 25_000)
        ->assertJsonPath('data.0.min_odds', '2.00')
        ->assertJsonPath('data.0.status', 'available')
        ->assertJsonPath('data.0.is_usable', true)
        ->assertJsonPath('data.1.id', $expired->id)
        ->assertJsonPath('data.1.status', 'expired')
        ->assertJsonPath('data.1.is_usable', false);
});

test('my usable free bets return 200 with those I can bet with now, soonest to expire first', function () {
    $player = User::factory()->create();
    $later = freeBetFor($player, ['expires_at' => now()->addDays(5)]);
    $sooner = freeBetFor($player, ['expires_at' => now()->addDay()]);
    freeBetFor($player, ['status' => FreeBetStatus::Used]);

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.free-bets.index', ['status' => 'usable']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $sooner->id)
        ->assertJsonPath('data.1.id', $later->id);
});

test('listing free bets with an unknown status returns 422', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson(route('api.v1.free-bets.index', ['status' => 'all']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('free bets return 401 without a token', function () {
    $this->getJson(route('api.v1.free-bets.index'))->assertUnauthorized();
});

test('placing a bet with a free bet returns 201 and takes nothing from the wallet', function () {
    $player = fundedPlayer(100_000);
    $freeBet = freeBetFor($player);

    $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [slipPick(selectionFor(pricedFixture()))],
        'free_bet_id' => $freeBet->id,
        'slip_key' => 'slip-1',
    ])
        ->assertCreated()
        ->assertJsonPath('data.stake_cents', 10_000)
        ->assertJsonPath('data.is_free_bet', true)
        ->assertJsonPath('data.potential_payout_cents', 11_000)
        ->assertJsonPath('data.bonus_percent', 0);

    expect(app(Ledger::class)->balanceOf($player))->toBe(100_000)
        ->and($freeBet->fresh()->status)->toBe(FreeBetStatus::Used);

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.free-bets.index'))
        ->assertJsonPath('data.0.status', 'used')
        ->assertJsonPath('data.0.bet_reference', Bet::sole()->reference);
});

test('placing a bet with another player\'s free bet returns 422 with free-bet-unavailable', function () {
    $player = fundedPlayer(100_000);

    $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [slipPick(selectionFor(pricedFixture()))],
        'free_bet_id' => freeBetFor(User::factory()->create())->id,
        'slip_key' => 'slip-1',
    ])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'That free bet can no longer be used.', 'reason' => 'free-bet-unavailable']);

    expect(Bet::query()->exists())->toBeFalse();
});

test('placing a free bet below its minimum odds returns 422 with free-bet-min-odds', function () {
    $player = fundedPlayer(100_000);

    $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [slipPick(selectionFor(pricedFixture()))],
        'free_bet_id' => freeBetFor($player, ['min_odds' => '3.00'])->id,
        'slip_key' => 'slip-1',
    ])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'This free bet needs total odds of at least 3.00.', 'reason' => 'free-bet-min-odds']);
});

test('placing a bet with both a stake and a free bet returns 422', function () {
    $player = fundedPlayer(100_000);

    $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [slipPick(selectionFor(pricedFixture()))],
        'stake' => '100',
        'free_bet_id' => freeBetFor($player)->id,
        'slip_key' => 'slip-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['free_bet_id' => 'Leave out the stake when betting with a free bet: the free bet is the stake.']);
});

test('placing a bet with neither a stake nor a free bet returns 422', function () {
    $this->actingAs(fundedPlayer(), 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => [slipPick(selectionFor(pricedFixture()))],
        'slip_key' => 'slip-1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['stake' => 'Enter a stake, or bet with a free bet.']);
});

test('a multi returns 201 with its multibet bonus, and the bonus paid once it wins', function () {
    $player = fundedPlayer(100_000);
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture()];

    $reference = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.bets.store'), [
        'picks' => array_map(fn ($fixture): array => slipPick(selectionFor($fixture)), $fixtures),
        'stake' => '100',
        'slip_key' => 'slip-1',
    ])
        ->assertCreated()
        ->assertJsonPath('data.bonus_percent', 3)
        ->assertJsonPath('data.potential_payout_cents', 95_078)
        ->json('data.reference');

    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.bets.show', $reference))
        ->assertOk()
        ->assertJsonPath('data.bonus_cents', 2_478)
        ->assertJsonPath('data.payout_cents', 95_078);
});

test('quoting a slip returns 200 with the multibet bonus and the next tier', function () {
    $picks = array_map(fn (): array => slipPick(selectionFor(pricedFixture())), range(1, 3));

    $this->postJson(route('api.v1.slip.quote'), ['picks' => $picks, 'stake' => '100'])
        ->assertOk()
        ->assertJsonPath('possible_win_cents', 95_078)
        ->assertJsonPath('multibet_bonus.qualifying_picks', 3)
        ->assertJsonPath('multibet_bonus.percent', 3)
        ->assertJsonPath('multibet_bonus.amount_cents', 2_478)
        ->assertJsonPath('multibet_bonus.min_price', '1.30')
        ->assertJsonPath('multibet_bonus.next_tier', ['more_picks' => 1, 'percent' => 5]);
});

test('quoting a free bet returns 200 with only the winnings above the stake and no bonus', function () {
    $picks = array_map(fn (): array => slipPick(selectionFor(pricedFixture())), range(1, 3));

    $this->postJson(route('api.v1.slip.quote'), ['picks' => $picks, 'stake' => '100', 'free_bet' => true])
        ->assertOk()
        ->assertJsonPath('possible_win_cents', 82_600)
        ->assertJsonPath('multibet_bonus.percent', 0)
        ->assertJsonPath('multibet_bonus.amount_cents', 0)
        ->assertJsonPath('multibet_bonus.next_tier', null);
});
