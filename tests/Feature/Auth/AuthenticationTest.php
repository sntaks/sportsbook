<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate with their phone number written any common way', function (string $written) {
    $user = User::factory()->create(['phone' => '254712345678']);

    $response = $this->post(route('login.store'), [
        'phone' => $written,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
})->with([
    'local' => '0712345678',
    'local with spaces' => '0712 345 678',
    'international' => '+254712345678',
    'country code' => '254712345678',
]);

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('login attempts are throttled per phone number however it is written', function () {
    $user = User::factory()->create(['phone' => '254712345678']);

    foreach (['0712345678', '+254712345678', '254712345678', '0712 345 678', '712345678'] as $written) {
        $this->post(route('login.store'), ['phone' => $written, 'password' => 'wrong-password']);
    }

    $this->post(route('login.store'), ['phone' => $user->phone, 'password' => 'password'])
        ->assertTooManyRequests();

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
