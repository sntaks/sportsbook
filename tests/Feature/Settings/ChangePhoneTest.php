<?php

use App\Models\User;
use Livewire\Livewire;

test('a new number replaces the old one once its code comes back', function () {
    $sms = fakeSms();
    $user = User::factory()->create(['phone' => '254711111111']);
    $this->actingAs($user);

    Livewire::test('pages::settings.change-phone')
        ->set('phone', '0722 222 222')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('pendingPhone', '254722222222')
        ->set('code', $sms->lastCodeFor('254722222222'))
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSet('pendingPhone', null);

    expect($user->fresh()->phone)->toBe('254722222222')
        ->and($sms->sent)->toHaveCount(1);
});

test('the old number stays until the right code is entered', function () {
    $sms = fakeSms();
    $user = User::factory()->create(['phone' => '254711111111']);
    $this->actingAs($user);

    $component = Livewire::test('pages::settings.change-phone')
        ->set('phone', '0722222222')
        ->call('sendCode');
    $wrong = $sms->lastCodeFor('254722222222') === '000000' ? '111111' : '000000';

    $component->set('code', $wrong)
        ->call('confirm')
        ->assertHasErrors(['code' => 'That code is not correct.']);

    expect($user->fresh()->phone)->toBe('254711111111');
});

test('cannot move to a number that belongs to another account', function () {
    $sms = fakeSms();
    User::factory()->create(['phone' => '254722222222']);
    $this->actingAs(User::factory()->create(['phone' => '254711111111']));

    Livewire::test('pages::settings.change-phone')
        ->set('phone', '0722222222')
        ->call('sendCode')
        ->assertHasErrors(['phone' => 'This number belongs to another account.']);

    expect($sms->sent)->toBeEmpty();
});

test('cannot change to the number already on the account', function () {
    fakeSms();
    $this->actingAs(User::factory()->create(['phone' => '254711111111']));

    Livewire::test('pages::settings.change-phone')
        ->set('phone', '0711111111')
        ->call('sendCode')
        ->assertHasErrors(['phone' => 'That is already your number.']);
});

test('a code sent for someone else cannot change this account', function () {
    $sms = fakeSms();
    $player = User::factory()->create(['phone' => '254711111111']);
    $other = User::factory()->create(['phone' => '254733333333']);

    $this->actingAs($other);
    Livewire::test('pages::settings.change-phone')->set('phone', '0722222222')->call('sendCode');

    $this->actingAs($player);
    Livewire::test('pages::settings.change-phone')
        ->set('code', $sms->lastCodeFor('254722222222'))
        ->call('confirm')
        ->assertHasErrors(['code' => 'That code has expired. Please request a new one.']);

    expect($player->fresh()->phone)->toBe('254711111111');
});
