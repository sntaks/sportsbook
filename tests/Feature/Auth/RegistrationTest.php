<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Fill in the sign-up details and ask for a code.
 */
function startSignUp(array $overrides = []): Testable
{
    return Livewire::test('pages::auth.register')
        ->set('phone', $overrides['phone'] ?? '0712 345 678')
        ->set('password', $overrides['password'] ?? 'password')
        ->set('password_confirmation', $overrides['password_confirmation'] ?? $overrides['password'] ?? 'password')
        ->set('adult', $overrides['adult'] ?? true)
        ->call('sendCode');
}

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk()->assertSee('Phone number');
});

test('sending the details texts a code but does not create the account yet', function () {
    $sms = fakeSms();

    startSignUp()
        ->assertHasNoErrors()
        ->assertSet('pendingPhone', '254712345678')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    expect($sms->sent)->toHaveCount(1)
        ->and(User::count())->toBe(0);
});

test('the right code creates a verified account and logs the player in', function () {
    $sms = fakeSms();
    Event::fake([Registered::class]);

    startSignUp()
        ->set('code', $sms->lastCodeFor('254712345678'))
        ->call('createAccount')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::sole();

    expect($user->phone)->toBe('254712345678')
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->adult_confirmed_at)->not->toBeNull()
        ->and($user->name)->toBeNull()
        ->and($user->email)->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(Registered::class);
});

test('a wrong code creates no account', function () {
    $sms = fakeSms();

    $component = startSignUp();
    $wrong = $sms->lastCodeFor('254712345678') === '000000' ? '111111' : '000000';

    $component->set('code', $wrong)
        ->call('createAccount')
        ->assertHasErrors(['code' => 'That code is not correct.']);

    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('a sign-up left for longer than the code lasts has to start again', function () {
    $sms = fakeSms();
    $component = startSignUp();

    $this->travel(11)->minutes();

    $component->set('code', $sms->lastCodeFor('254712345678'))
        ->call('createAccount')
        ->assertHasErrors(['code' => 'That code has expired. Please request a new one.']);

    expect(User::count())->toBe(0);
});

test('a new code can be requested once the resend wait is over', function () {
    $sms = fakeSms();
    $component = startSignUp();

    $this->travel(61)->seconds();

    $component->call('resendCode')->assertHasNoErrors();

    expect($sms->sent)->toHaveCount(2);
});

test('asking for a new code after the sign-up has lapsed says to start again and sends nothing', function () {
    $sms = fakeSms();
    $component = startSignUp();

    $this->travel(11)->minutes();

    $component->call('resendCode')
        ->assertHasErrors(['phone' => 'That code has expired. Please request a new one.']);

    expect($sms->sent)->toHaveCount(1);
});

test('a number that already has an account cannot sign up again', function () {
    $sms = fakeSms();
    User::factory()->create(['phone' => '254712345678']);

    startSignUp(['phone' => '+254 712 345 678'])
        ->assertHasErrors(['phone' => 'This number already has an account. Log in instead.']);

    expect($sms->sent)->toBeEmpty();
});

test('the player must confirm they are 18 or older', function () {
    fakeSms();

    startSignUp(['adult' => false])
        ->assertHasErrors(['adult' => 'You must be 18 or older to sign up.']);
});

test('only Kenyan mobile numbers can sign up', function () {
    fakeSms();

    startSignUp(['phone' => '020 123 4567'])
        ->assertHasErrors(['phone' => 'Enter a valid Kenyan mobile number.']);
});

test('the passwords must match', function () {
    fakeSms();

    startSignUp(['password' => 'password', 'password_confirmation' => 'different'])
        ->assertHasErrors(['password' => 'confirmed']);
});

test('logged-in players are sent away from the sign-up page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('register'))
        ->assertRedirect(route('home'));
});
