<?php

use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('a player withdraws from the wallet page and sees it on its way', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $this->actingAs(fundedPlayer(100_000));

    Livewire::test('pages::sportsbook.wallet')
        ->set('withdrawAmount', '400')
        ->call('withdraw')
        ->assertHasNoErrors()
        ->assertDispatched('wallet-updated')
        ->assertSee('Your withdrawal of KES 400.00 is on its way to your M-Pesa.')
        ->assertSee('KES 600.00');

    expect(Withdrawal::sole()->status)->toBe(WithdrawalStatus::Queued);
});

test('a large withdrawal tells the player it is being reviewed', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $this->actingAs(fundedPlayer(3_000_000));

    Livewire::test('pages::sportsbook.wallet')
        ->set('withdrawAmount', '20000')
        ->call('withdraw')
        ->assertSee('We are reviewing your withdrawal of KES 20,000.00.');
});

test('withdrawal errors show under the withdraw form, not the deposit form', function () {
    $this->actingAs(fundedPlayer(1_000));

    Livewire::test('pages::sportsbook.wallet')
        ->set('withdrawAmount', '500')
        ->call('withdraw')
        ->assertHasErrors(['withdrawAmount' => 'You can withdraw up to KES 10.00.'])
        ->assertHasNoErrors('amount');
});

test('a player on a break sees why and gets no deposit form, but can still withdraw', function () {
    $this->actingAs(fundedPlayer(100_000, ['exclusion_type' => 'cool-off', 'excluded_until' => now()->addDays(7)]));

    $this->get(route('wallet'))
        ->assertOk()
        ->assertSee('You are on a cool-off until')
        ->assertDontSee('data-test="deposit-button"', escape: false)
        ->assertSee('data-test="withdraw-button"', escape: false);
});

test('a suspended player sees why and gets neither form', function () {
    $this->actingAs(fundedPlayer(100_000, ['status' => UserStatus::Suspended]));

    $this->get(route('wallet'))
        ->assertOk()
        ->assertSee('Your account is suspended')
        ->assertDontSee('data-test="deposit-button"', escape: false)
        ->assertDontSee('data-test="withdraw-button"', escape: false);
});
