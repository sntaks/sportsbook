<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('forgot password screen can be rendered', function () {
    $this->get(route('password.request'))->assertOk()->assertSee('Phone number');
});

test('a reset code is texted to a number with an account', function () {
    $sms = fakeSms();
    User::factory()->create(['phone' => '254712345678']);

    Livewire::test('pages::auth.forgot-password')
        ->set('phone', '0712345678')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('pendingPhone', '254712345678');

    expect($sms->sent)->toHaveCount(1);
});

test('a number with no account gets the same answer but no SMS', function () {
    $sms = fakeSms();

    Livewire::test('pages::auth.forgot-password')
        ->set('phone', '0712345678')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('pendingPhone', '254712345678');

    expect($sms->sent)->toBeEmpty();
});

test('the right code sets the new password and signs out everywhere else', function () {
    $sms = fakeSms();
    $user = User::factory()->create(['phone' => '254712345678', 'remember_token' => 'old-remember-token']);
    $user->createToken('android');
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    Livewire::test('pages::auth.forgot-password')
        ->set('phone', '0712345678')
        ->call('sendCode')
        ->set('code', $sms->lastCodeFor('254712345678'))
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-remember-token')
        ->and($user->tokens()->count())->toBe(0);
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    $this->assertGuest();
});

test('a wrong code leaves the password unchanged', function () {
    $sms = fakeSms();
    $user = User::factory()->create(['phone' => '254712345678']);

    $component = Livewire::test('pages::auth.forgot-password')
        ->set('phone', '0712345678')
        ->call('sendCode');
    $wrong = $sms->lastCodeFor('254712345678') === '000000' ? '111111' : '000000';

    $component->set('code', $wrong)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasErrors(['code' => 'That code is not correct.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('rejects numbers that are not Kenyan mobiles', function () {
    fakeSms();

    Livewire::test('pages::auth.forgot-password')
        ->set('phone', '12345')
        ->call('sendCode')
        ->assertHasErrors(['phone' => 'Enter a valid Kenyan mobile number.']);
});
