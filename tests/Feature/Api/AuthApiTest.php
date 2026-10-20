<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

/**
 * Start signing up over the API with valid details.
 *
 * @param  array<string, mixed>  $overrides
 */
function startApiSignUp(array $overrides = []): TestResponse
{
    return test()->postJson(route('api.v1.auth.register'), [
        'phone' => '0712 345 678',
        'password' => 'password',
        'password_confirmation' => 'password',
        'adult' => true,
        ...$overrides,
    ]);
}

test('starting sign-up returns 202, texts a code and creates no account yet', function () {
    $sms = fakeSms();

    startApiSignUp()
        ->assertAccepted()
        ->assertJsonPath('phone', '254712345678');

    expect($sms->sent)->toHaveCount(1)
        ->and(User::count())->toBe(0);
});

test('starting sign-up without confirming age returns 422', function () {
    $sms = fakeSms();

    startApiSignUp(['adult' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['adult' => 'You must be 18 or older to sign up.']);

    expect($sms->sent)->toBeEmpty();
});

test('verifying sign-up returns 201 with a 90-day token for the new account', function () {
    $sms = fakeSms();
    startApiSignUp();

    $response = $this->postJson(route('api.v1.auth.register.verify'), [
        'phone' => '254712345678',
        'code' => $sms->lastCodeFor('254712345678'),
        'device_name' => 'Pixel 8',
    ]);

    $user = User::sole();
    $response->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.phone', '254712345678')
        ->assertJsonPath('user.balance_cents', 0);
    $token = PersonalAccessToken::findToken($response->json('token'));
    expect($user->phone_verified_at)->not->toBeNull()
        ->and($token->tokenable->is($user))->toBeTrue()
        ->and($token->name)->toBe('Pixel 8')
        ->and($token->expires_at->diffInDays(now()->addDays(90)))->toBeLessThan(1);
});

test('signing up with a friend\'s invite code returns 201 and links the account to them', function () {
    $sms = fakeSms();
    $friend = User::factory()->create(['referral_code' => 'ABCD2345']);

    startApiSignUp(['referral_code' => ' abcd2345 '])->assertAccepted();

    $this->postJson(route('api.v1.auth.register.verify'), [
        'phone' => '254712345678',
        'code' => $sms->lastCodeFor('254712345678'),
        'device_name' => 'Pixel 8',
    ])->assertCreated();

    expect(User::query()->where('phone', '254712345678')->sole()->referred_by_id)->toBe($friend->id);
});

test('starting sign-up with an unknown invite code returns 422 and sends no code', function () {
    $sms = fakeSms();

    startApiSignUp(['referral_code' => 'NOPE2345'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['referral_code' => 'That invite code isn\'t valid.']);

    expect($sms->sent)->toBeEmpty();
});

test('verifying sign-up with a wrong code returns 422 and creates no account', function () {
    $sms = fakeSms();
    startApiSignUp();
    $wrong = $sms->lastCodeFor('254712345678') === '000000' ? '111111' : '000000';

    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '254712345678', 'code' => $wrong, 'device_name' => 'Pixel 8'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'That code is not correct.']);

    expect(User::count())->toBe(0);
});

test('resending a sign-up code returns 202 and texts a new code', function () {
    $sms = fakeSms();
    startApiSignUp();
    $this->travel(61)->seconds();

    $this->postJson(route('api.v1.auth.register.resend'), ['phone' => '0712345678'])->assertAccepted();

    expect($sms->sent)->toHaveCount(2);
});

test('resending to a number that has not started signing up returns 422 and sends nothing', function () {
    $sms = fakeSms();

    $this->postJson(route('api.v1.auth.register.resend'), ['phone' => '0712345678'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Your sign-up has expired. Please start again.']);

    expect($sms->sent)->toBeEmpty();
});

test('logging in returns 200 with a token for any common phone format', function (string $phone) {
    $user = User::factory()->create(['phone' => '254712345678']);

    $response = $this->postJson(route('api.v1.auth.login'), ['phone' => $phone, 'password' => 'password', 'device_name' => 'iPhone']);

    $response->assertOk()->assertJsonPath('user.id', $user->id);
    expect(PersonalAccessToken::findToken($response->json('token'))->tokenable->is($user))->toBeTrue();
})->with(['0712345678', '+254712345678', '254712345678']);

test('logging in with a wrong password returns 422 and issues no token', function () {
    User::factory()->create(['phone' => '254712345678']);

    $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'wrong-password', 'device_name' => 'iPhone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => __('auth.failed')]);

    expect(PersonalAccessToken::count())->toBe(0);
});

test('logging in is limited to 5 attempts a minute per number with 429', function () {
    User::factory()->create(['phone' => '254712345678']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'wrong-password', 'device_name' => 'iPhone'])
            ->assertUnprocessable();
    }

    $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'password', 'device_name' => 'iPhone'])
        ->assertTooManyRequests();
});

describe('with two-factor authentication on', function () {
    beforeEach(function () {
        $this->secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $this->player = User::factory()->withTwoFactor()->create([
            'phone' => '254712345678',
            'two_factor_secret' => encrypt($this->secret),
        ]);
    });

    test('logging in without a code returns 422 asking for one', function () {
        $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'password', 'device_name' => 'iPhone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Enter the code from your authenticator app, or a recovery code.']);

        expect(PersonalAccessToken::count())->toBe(0);
    });

    test('logging in with the authenticator code returns 200', function () {
        $code = app(Google2FA::class)->getCurrentOtp($this->secret);

        $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'password', 'code' => $code, 'device_name' => 'iPhone'])
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);
    });

    test('logging in with a recovery code returns 200 and uses the code up', function () {
        $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'password', 'code' => 'recovery-code-1', 'device_name' => 'iPhone'])
            ->assertOk();

        expect($this->player->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
    });

    test('logging in with a wrong code returns 422', function () {
        $this->postJson(route('api.v1.auth.login'), ['phone' => '0712345678', 'password' => 'password', 'code' => '000000', 'device_name' => 'iPhone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'The provided two factor authentication code was invalid.']);

        expect(PersonalAccessToken::count())->toBe(0);
    });
});

test('account endpoints return 401 without a token', function () {
    $this->getJson(route('api.v1.me'))->assertUnauthorized();
});

test('a token stops working with 401 once it has expired', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone', ['*'], now()->addDays(90))->plainTextToken;

    $this->travel(91)->days();

    $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
});

test('logging out returns 204 and revokes only the token used', function () {
    $user = User::factory()->create();
    $phoneToken = $user->createToken('iPhone')->plainTextToken;
    $user->createToken('Tablet');

    $this->withToken($phoneToken)->postJson(route('api.v1.auth.logout'))->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['Tablet']);
});

test('asking for a reset code returns 202 the same way for a number with no account, without an SMS', function () {
    $sms = fakeSms();

    $this->postJson(route('api.v1.auth.forgot-password'), ['phone' => '0712345678'])
        ->assertAccepted()
        ->assertJsonPath('phone', '254712345678');

    expect($sms->sent)->toBeEmpty();
});

test('resetting the password returns 200 and revokes every token', function () {
    $sms = fakeSms();
    $user = User::factory()->create(['phone' => '254712345678']);
    $user->createToken('iPhone');
    $user->createToken('Tablet');
    $this->postJson(route('api.v1.auth.forgot-password'), ['phone' => '0712345678'])->assertAccepted();

    $this->postJson(route('api.v1.auth.reset-password'), [
        'phone' => '254712345678',
        'code' => $sms->lastCodeFor('254712345678'),
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});
