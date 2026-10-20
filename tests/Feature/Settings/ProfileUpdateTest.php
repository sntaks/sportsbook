<?php

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Wallet\Ledger;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create(['phone' => '254712345678']));

    $this->get(route('profile.edit'))->assertOk()->assertSee('0712 *** 678');
});

test('account settings use the player layout, with tabs for profile, security and limits', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="settings-nav"', false)
        ->assertSeeInOrder([route('profile.edit'), route('security.edit'), route('responsible-gambling.settings')])
        ->assertSee(route('my-bets'));

    $this->get(route('responsible-gambling.settings'))->assertOk()->assertSee('data-test="settings-nav"', false);
    $this->get('settings/appearance')->assertNotFound();
});

test('the settings tab stays marked as current after the page updates', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.profile')
        ->call('updateProfileInformation')
        ->assertSeeHtmlInOrder([route('profile.edit'), 'aria-current="page"', route('security.edit')]);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('name and email are optional and clearing them stores nothing', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', '')
        ->set('email', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBeNull()
        ->and($user->email)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('an account that has moved money cannot be deleted by the player', function () {
    $user = User::factory()->create();
    app(Ledger::class)->post($user, WalletTransactionType::Deposit, 50_000, 'test:1');

    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password' => 'Accounts with deposits or transactions cannot be deleted here. Contact support to close your account.']);

    expect($user->fresh())->not->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
