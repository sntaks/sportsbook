<?php

use App\Enums\MarketStatus;
use App\Models\Admin;
use App\Models\Fixture;
use App\Models\Market;
use App\Models\Selection;
use App\Sportsbook\Trading\MarketCannotBeReopened;
use App\Sportsbook\Trading\ReopenMarket;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    config(['sportsbook.odds.stale_after_hours' => 26]);
});

test('reopens a freshly priced market for an upcoming fixture and logs who did it', function () {
    $admin = Admin::factory()->create();
    $market = Market::factory()->suspended()->has(Selection::factory())->create();

    app(ReopenMarket::class)->handle($market, $admin);

    expect($market->fresh()->status)->toBe(MarketStatus::Open)
        ->and($market->fresh()->suspension_reason)->toBeNull()
        ->and(Activity::query()->where('event', 'market-reopened')->sole()->causer->is($admin))->toBeTrue();
});

test('refuses to reopen a market that cannot take bets right now', function (Closure $makeMarket, string $message) {
    $market = $makeMarket();

    expect(fn () => app(ReopenMarket::class)->handle($market, Admin::factory()->create()))
        ->toThrow(MarketCannotBeReopened::class, $message);

    expect($market->fresh()->status)->toBe(MarketStatus::Suspended);
})->with([
    'kicked off' => [fn () => Market::factory()->for(Fixture::factory()->kickedOff())->suspended()->has(Selection::factory())->create(), 'has kicked off'],
    'stale odds' => [fn () => Market::factory()->pricedHoursAgo(30)->suspended()->has(Selection::factory())->create(), 'out of date'],
    'never priced' => [fn () => Market::factory()->suspended()->create(), 'no prices yet'],
]);
