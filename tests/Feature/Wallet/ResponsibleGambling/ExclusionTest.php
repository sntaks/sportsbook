<?php

use App\Enums\ExclusionType;
use App\Enums\MpesaDepositStatus;
use App\Enums\UserStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Wallet\Deposits\StartDeposit;
use App\Wallet\Ledger;
use App\Wallet\ResponsibleGambling\ExcludePlayer;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

test('a cool-off pauses deposits until it ends and is in the activity log', function () {
    $this->freezeSecond();
    $player = User::factory()->create();

    app(ExcludePlayer::class)->handle($player, ExclusionType::CoolOff, '7d');

    expect($player->fresh())
        ->isExcluded()->toBeTrue()
        ->canDeposit()->toBeFalse()
        ->canWithdraw()->toBeTrue()
        ->excluded_until->toEqual(now()->addDays(7));
    expect(Activity::query()->where('event', 'player-excluded')->sole()->causer->is($player))->toBeTrue();

    $this->travel(7)->days();
    $this->travel(1)->second();

    expect($player->fresh()->canDeposit())->toBeTrue();
});

test('a break can be made longer but never shorter', function () {
    $player = User::factory()->create();
    app(ExcludePlayer::class)->handle($player, ExclusionType::SelfExclusion, '1y');

    expect(fn () => app(ExcludePlayer::class)->handle($player->fresh(), ExclusionType::CoolOff, '30d'))
        ->toThrow(ValidationException::class);

    app(ExcludePlayer::class)->handle($player->fresh(), ExclusionType::SelfExclusion, '5y');

    expect($player->fresh()->excluded_until->isAfter(now()->addYears(4)))->toBeTrue();
});

test('refuses a length that does not belong to the kind of break', function () {
    expect(fn () => app(ExcludePlayer::class)->handle(User::factory()->create(), ExclusionType::CoolOff, '5y'))
        ->toThrow(ValidationException::class, 'Choose how long the break should last.');
});

test('excluded and suspended players cannot start an STK deposit', function (array $attributes, string $message) {
    fakeMpesa();

    expect(fn () => app(StartDeposit::class)->handle(User::factory()->create($attributes), '500'))
        ->toThrow(ValidationException::class, $message);
})->with([
    'on a break' => [['excluded_until' => '2099-01-01 00:00:00'], 'Deposits are paused until'],
    'suspended' => [['status' => UserStatus::Suspended], 'Your account is suspended. Please contact support.'],
]);

test('Paybill validation rejects players who may not deposit', function (array $attributes) {
    fakeMpesa();
    User::factory()->create(['phone' => '254712345678', ...$attributes]);

    $this->postJson(mpesaCallbackUrl('c2b/validation'), ['TransID' => 'TX1', 'TransAmount' => '100.00', 'BillRefNumber' => '0712345678'])
        ->assertExactJson(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Rejected']);
})->with([
    'on a break' => [['excluded_until' => '2099-01-01 00:00:00']],
    'suspended' => [['status' => UserStatus::Suspended]],
]);

test('a Paybill payment from an excluded player is still credited, since the money has left their M-Pesa', function () {
    fakeMpesa();
    $player = User::factory()->create(['phone' => '254712345678', 'excluded_until' => now()->addYear()]);

    $this->postJson(mpesaCallbackUrl('c2b/confirmation'), ['TransID' => 'TX1', 'TransAmount' => '100.00', 'BillRefNumber' => '0712345678', 'MSISDN' => '2547 ***** 678'])->assertOk();

    expect(MpesaDeposit::sole()->status)->toBe(MpesaDepositStatus::Completed)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(10_000);
});
