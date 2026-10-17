<?php

use App\Enums\MarketStatus;
use App\Enums\SuspensionReason;
use App\Models\Admin;
use App\Models\Market;
use App\Sportsbook\Trading\SuspendMarket;
use Spatie\Activitylog\Models\Activity;

test('suspends a market as a manual suspension and logs who did it and why', function () {
    $admin = Admin::factory()->create();
    $market = Market::factory()->create();

    app(SuspendMarket::class)->handle($market, $admin, 'Possible match fixing report');

    $activity = Activity::query()->where('event', 'market-suspended')->sole();
    expect($market->fresh()->status)->toBe(MarketStatus::Suspended)
        ->and($market->fresh()->suspension_reason)->toBe(SuspensionReason::Manual)
        ->and($activity->causer->is($admin))->toBeTrue()
        ->and($activity->properties->get('reason'))->toBe('Possible match fixing report');
});
