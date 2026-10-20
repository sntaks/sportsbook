<?php

use App\Enums\DepositLimitPeriod;
use App\Enums\ExclusionType;
use App\Models\User;
use App\Wallet\ResponsibleGambling\SetDepositLimit;

test('deposit limits return 200 with all three periods, unset ones as null', function () {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Weekly, '5000');

    $response = $this->actingAs($player, 'sanctum')->getJson(route('api.v1.deposit-limits.index'));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.period', 'daily')
        ->assertJsonPath('data.0.amount_cents', null)
        ->assertJsonPath('data.1.period', 'weekly')
        ->assertJsonPath('data.1.amount_cents', 500_000)
        ->assertJsonPath('allowance_cents', 500_000)
        ->assertJsonPath('raise_delay_hours', 24);
});

test('setting a first deposit limit returns 200 and applies at once', function () {
    $player = User::factory()->create();

    $this->actingAs($player, 'sanctum')
        ->putJson(route('api.v1.deposit-limits.update', 'daily'), ['amount' => 1000])
        ->assertOk()
        ->assertJsonPath('data.amount_cents', 100_000)
        ->assertJsonPath('data.has_pending_change', false);

    expect($player->depositLimits()->sole()->effectiveAmount())->toBe(100_000);
});

test('raising a deposit limit returns 200 with the raise waiting', function () {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');

    $this->actingAs($player, 'sanctum')
        ->putJson(route('api.v1.deposit-limits.update', 'daily'), ['amount' => 2000])
        ->assertOk()
        ->assertJsonPath('data.amount_cents', 100_000)
        ->assertJsonPath('data.has_pending_change', true)
        ->assertJsonPath('data.pending_amount_cents', 200_000);
});

test('setting a limit for an unknown period returns 404', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->putJson('/api/v1/deposit-limits/yearly', ['amount' => 1000])
        ->assertNotFound();
});

test('taking a break returns 201 and stops betting and deposits', function () {
    $player = User::factory()->create();

    $response = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.exclusions.store'), [
        'type' => 'cool-off',
        'duration' => '7d',
        'password' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.exclusion.type', 'cool-off')
        ->assertJsonPath('data.can_bet', false)
        ->assertJsonPath('data.can_deposit', false);
    expect($player->fresh())
        ->exclusion_type->toBe(ExclusionType::CoolOff)
        ->excluded_until->toBeInstanceOf(DateTimeInterface::class);
});

test('taking a break with the wrong password returns 422 and starts no break', function () {
    $player = User::factory()->create();

    $this->actingAs($player, 'sanctum')
        ->postJson(route('api.v1.exclusions.store'), ['type' => 'cool-off', 'duration' => '7d', 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => 'That password is not correct.']);

    expect($player->fresh()->isExcluded())->toBeFalse();
});

test('taking a break with a duration its type does not offer returns 422', function () {
    $player = User::factory()->create();

    $this->actingAs($player, 'sanctum')
        ->postJson(route('api.v1.exclusions.store'), ['type' => 'cool-off', 'duration' => '5y', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration' => 'Choose how long the break should last.']);

    expect($player->fresh()->isExcluded())->toBeFalse();
});

test('setting the odds change policy returns 200 and remembers it', function () {
    $player = User::factory()->create();

    $this->actingAs($player, 'sanctum')
        ->putJson(route('api.v1.me.odds-change-policy'), ['odds_change_policy' => 'any'])
        ->assertOk()
        ->assertJsonPath('data.odds_change_policy', 'any');

    expect($player->fresh()->odds_change_policy->value)->toBe('any');
});

test('setting an unknown odds change policy returns 422', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->putJson(route('api.v1.me.odds-change-policy'), ['odds_change_policy' => 'sometimes'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['odds_change_policy']);
});

test('responsible gambling endpoints return 401 without a token', function () {
    $this->getJson(route('api.v1.deposit-limits.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.exclusions.store'), [])->assertUnauthorized();
});
