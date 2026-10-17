<?php

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Enums\SuspensionReason;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Market;
use App\Models\OddsSnapshot;
use App\Models\Selection;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    config(['sportsbook.odds.margin' => 0.06, 'sportsbook.odds.minimum_bookmakers' => 3]);
    $this->travelTo('2026-10-18 09:00:00');
    Http::fake(['odds-api.test/v4/sports/soccer_epl/odds*' => Http::response(oddsApiFixture('odds-soccer_epl'), 200, ['x-requests-last' => '2', 'x-requests-remaining' => '486'])]);
    $this->competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
});

function syncEplOdds(Competition $competition): void
{
    app(SyncOdds::class)->handle($competition, [MarketType::MatchResult, MarketType::Totals]);
}

/**
 * @return array<string, string|null>
 */
function offeredPrices(Market $market): array
{
    return $market->selections()->orderBy('id')->get()->mapWithKeys(fn (Selection $selection) => [$selection->name => $selection->price()])->all();
}

test('prices 1X2 from the median bookmaker after margins and opens the market', function () {
    syncEplOdds($this->competition);

    $market = Fixture::firstWhere('external_id', 'evt-arsenal-spurs')->markets()->where('key', 'h2h')->sole();
    expect($market->status)->toBe(MarketStatus::Open)
        ->and($market->bookmaker_count)->toBe(3)
        ->and(offeredPrices($market))->toBe(['Arsenal' => '1.88', 'Draw' => '3.77', 'Tottenham Hotspur' => '3.77'])
        ->and(OddsSnapshot::whereIn('selection_id', $market->selections()->select('id'))->count())->toBe(3);
});

test('creates a goal line market only when enough bookmakers price it', function () {
    syncEplOdds($this->competition);

    $fixture = Fixture::firstWhere('external_id', 'evt-arsenal-spurs');
    $twoAndAHalf = $fixture->markets()->where('key', 'totals:2.5')->sole();
    expect(offeredPrices($twoAndAHalf))->toBe(['Over 2.5' => '1.88', 'Under 2.5' => '1.88'])
        ->and($twoAndAHalf->status)->toBe(MarketStatus::Open)
        ->and($fixture->markets()->where('key', 'totals:3.5')->exists())->toBeFalse();
});

test('suspends a 1X2 market that fewer than three bookmakers price', function () {
    syncEplOdds($this->competition);

    $market = Fixture::firstWhere('external_id', 'evt-chelsea-everton')->markets()->where('key', 'h2h')->sole();
    expect($market->status)->toBe(MarketStatus::Suspended)
        ->and($market->suspension_reason)->toBe(SuspensionReason::InsufficientBookmakers)
        ->and($market->bookmaker_count)->toBe(2)
        ->and($market->selections()->exists())->toBeFalse();
});

test('does not duplicate markets or price history when nothing changed', function () {
    syncEplOdds($this->competition);
    syncEplOdds($this->competition);

    expect(Fixture::count())->toBe(2)
        ->and(Market::count())->toBe(3)
        ->and(OddsSnapshot::count())->toBe(5);
});

test('keeps an admin override while the feed price underneath it updates', function () {
    $fixture = Fixture::factory()->for($this->competition)->create(['external_id' => 'evt-arsenal-spurs']);
    $market = Market::factory()->for($fixture)->create();
    $home = Selection::factory()->for($market)->overridden('2.50')->create(['outcome' => 'home', 'feed_price' => '1.70']);

    syncEplOdds($this->competition);

    $home->refresh();
    expect($home->feed_price)->toBe('1.88')
        ->and($home->override_price)->toBe('2.50')
        ->and($home->price())->toBe('2.50')
        ->and($home->snapshots()->exists())->toBeFalse();
});

test('reopens markets suspended for pricing reasons but not ones an admin suspended', function (SuspensionReason $reason, MarketStatus $expected) {
    $fixture = Fixture::factory()->for($this->competition)->create(['external_id' => 'evt-arsenal-spurs']);
    $market = Market::factory()->for($fixture)->suspended($reason)->create();

    syncEplOdds($this->competition);

    expect($market->fresh()->status)->toBe($expected);
})->with([
    'stale odds' => [SuspensionReason::StaleOdds, MarketStatus::Open],
    'too few bookmakers' => [SuspensionReason::InsufficientBookmakers, MarketStatus::Open],
    'suspended by an admin' => [SuspensionReason::Manual, MarketStatus::Suspended],
]);

test('keeps markets closed once the fixture has kicked off', function () {
    $this->travelTo('2026-10-20 14:30:00');

    syncEplOdds($this->competition);

    $market = Fixture::firstWhere('external_id', 'evt-arsenal-spurs')->markets()->where('key', 'h2h')->sole();
    expect($market->status)->toBe(MarketStatus::Suspended)
        ->and($market->suspension_reason)->toBe(SuspensionReason::KickedOff);
});
