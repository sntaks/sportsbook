<?php

use App\Enums\DepositLimitPeriod;
use App\Enums\ExclusionType;
use App\Models\User;
use Livewire\Livewire;

test('guests are sent to log in', function () {
    $this->get(route('responsible-gambling.settings'))->assertRedirect(route('login'));
});

test('a player sets a daily deposit limit', function () {
    $player = User::factory()->create();
    $this->actingAs($player);

    Livewire::test('pages::sportsbook.responsible-gambling')
        ->set('limits.daily', '1000')
        ->call('saveLimit', 'daily')
        ->assertHasNoErrors()
        ->assertSee('Your daily (last 24 hours) limit is saved and applies now.')
        ->assertSee('You can deposit up to KES 1,000.00 right now.');

    expect($player->depositLimits()->where('period', DepositLimitPeriod::Daily)->sole()->effectiveAmount())->toBe(100_000);
});

test('an invalid limit shows under its own field', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::sportsbook.responsible-gambling')
        ->set('limits.weekly', '5')
        ->call('saveLimit', 'weekly')
        ->assertHasErrors(['limits.weekly']);
});

test('starting a break needs the player\'s password', function () {
    $player = User::factory()->create();
    $this->actingAs($player);

    Livewire::test('pages::sportsbook.responsible-gambling')
        ->set('exclusionType', ExclusionType::SelfExclusion->value)
        ->set('duration', '6m')
        ->set('password', 'wrong-password')
        ->call('takeBreak')
        ->assertHasErrors(['password' => 'That password is not correct.']);

    expect($player->fresh()->isExcluded())->toBeFalse();

    Livewire::test('pages::sportsbook.responsible-gambling')
        ->set('exclusionType', ExclusionType::SelfExclusion->value)
        ->set('duration', '6m')
        ->set('password', 'password')
        ->call('takeBreak')
        ->assertHasNoErrors();

    expect($player->fresh())
        ->isExcluded()->toBeTrue()
        ->exclusion_type->toBe(ExclusionType::SelfExclusion);
});
