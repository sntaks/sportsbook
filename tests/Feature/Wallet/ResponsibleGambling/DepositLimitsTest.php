<?php

use App\Enums\DepositLimitPeriod;
use App\Enums\MpesaDepositStatus;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Wallet\Deposits\StartDeposit;
use App\Wallet\ResponsibleGambling\DepositAllowance;
use App\Wallet\ResponsibleGambling\SetDepositLimit;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function limitOf(User $player, DepositLimitPeriod $period = DepositLimitPeriod::Daily): ?int
{
    return $player->depositLimits()->where('period', $period)->first()?->effectiveAmount();
}

test('a first limit applies at once and is in the activity log', function () {
    $player = User::factory()->create();

    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');

    expect(limitOf($player))->toBe(100_000);
    expect(Activity::query()->where('event', 'deposit-limit-changed')->sole())
        ->causer->is($player)->toBeTrue()
        ->properties->get('to')->toBe(100_000);
});

test('lowering a limit applies at once', function () {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');

    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '500');

    expect(limitOf($player))->toBe(50_000);
});

test('raising or removing a limit waits 24 hours', function (?string $newLimit, ?int $after) {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');

    $limit = app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, $newLimit);

    expect($limit->hasWaitingChange())->toBeTrue()
        ->and(limitOf($player))->toBe(100_000);

    $this->travel(24)->hours();

    expect(limitOf($player))->toBe($after);
})->with([
    'raised' => ['5000', 500_000],
    'removed' => [null, null],
]);

test('cutting a limit cancels a raise that was still waiting', function () {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '5000');

    $limit = app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '200');
    $this->travel(25)->hours();

    expect($limit->fresh()->hasWaitingChange())->toBeFalse()
        ->and(limitOf($player))->toBe(20_000);
});

test('the allowance counts completed and pending deposits in the rolling window only', function () {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');
    MpesaDeposit::factory()->for($player)->create(['status' => MpesaDepositStatus::Completed, 'amount' => 30_000]);
    MpesaDeposit::factory()->for($player)->create(['status' => MpesaDepositStatus::Pending, 'amount' => 20_000]);
    MpesaDeposit::factory()->for($player)->create(['status' => MpesaDepositStatus::Failed, 'amount' => 90_000]);
    MpesaDeposit::factory()->for($player)->startedMinutesAgo(25 * 60)->create(['status' => MpesaDepositStatus::Completed, 'amount' => 90_000]);

    expect(app(DepositAllowance::class)->remaining($player))->toBe(50_000);
});

test('the tightest limit wins', function () {
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '5000');
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Monthly, '800');

    expect(app(DepositAllowance::class)->remaining($player))->toBe(80_000);
});

test('an STK deposit over the limit is refused and no prompt is sent', function () {
    fakeMpesa();
    $player = User::factory()->create();
    app(SetDepositLimit::class)->handle($player, DepositLimitPeriod::Daily, '1000');
    MpesaDeposit::factory()->for($player)->create(['status' => MpesaDepositStatus::Completed, 'amount' => 80_000]);

    expect(fn () => app(StartDeposit::class)->handle($player, '300'))
        ->toThrow(ValidationException::class, 'That would take you over your deposit limit. You can deposit up to KES 200.00 right now.');

    Http::assertNothingSent();
});

test('a limit must be whole shillings of at least the minimum deposit', function () {
    expect(fn () => app(SetDepositLimit::class)->handle(User::factory()->create(), DepositLimitPeriod::Daily, '5'))
        ->toThrow(ValidationException::class, 'A limit must be at least KES 10.');
});
