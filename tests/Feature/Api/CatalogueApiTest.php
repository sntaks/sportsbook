<?php

use App\Enums\MarketSource;
use App\Enums\MarketType;
use App\Models\Competition;
use App\Models\Market;
use App\Models\Selection;

test('sports return 200 with only synced competitions and their upcoming match counts', function () {
    $fixture = pricedFixture();
    Competition::factory()->for($fixture->competition->sport)->create(['name' => 'Not Offered', 'is_synced' => false]);

    $response = $this->getJson(route('api.v1.sports.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(1, 'data.0.competitions')
        ->assertJsonPath('data.0.competitions.0.slug', $fixture->competition->slug)
        ->assertJsonPath('data.0.competitions.0.upcoming_fixtures_count', 1);
});

test('fixtures return 200 with each match\'s 1X2 market and a count of all its markets', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);

    $response = $this->getJson(route('api.v1.fixtures.index'));

    $response->assertOk()
        ->assertJsonPath('data.0.id', $fixture->id)
        ->assertJsonPath('data.0.name', 'Arsenal v Chelsea')
        ->assertJsonPath('data.0.markets_count', 1)
        ->assertJsonPath('data.0.market.key', 'h2h')
        ->assertJsonPath('data.0.market.selections.0.price', '2.10');
});

test('fixtures return the market chosen for the list, or null when a match does not have it', function () {
    $withBtts = pricedFixture(['kicks_off_at' => now()->addHours(2)]);
    $btts = Market::factory()->for($withBtts)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    Selection::factory()->for($btts)->create(['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.72']);
    pricedFixture(['kicks_off_at' => now()->addHours(3)]);

    $response = $this->getJson(route('api.v1.fixtures.index', ['market' => 'btts']));

    $response->assertOk()
        ->assertJsonPath('data.0.market.key', 'btts')
        ->assertJsonPath('data.0.market.selections.0.price', '1.72')
        ->assertJsonPath('data.1.market', null);
});

test('fixtures with an unknown tab or market return 422', function () {
    $this->getJson(route('api.v1.fixtures.index', ['tab' => 'nonsense', 'market' => 'corners']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tab', 'market']);
});

test('fixtures filtered by competition return 200 with only that competition\'s matches', function () {
    $own = pricedFixture();
    pricedFixture();

    $response = $this->getJson(route('api.v1.fixtures.index', ['competition' => $own->competition->slug]));

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
});

test('fixtures for a competition that is not synced return 404', function () {
    $fixture = pricedFixture();
    $fixture->competition->update(['is_synced' => false]);

    $this->getJson(route('api.v1.fixtures.index', ['competition' => $fixture->competition->slug]))->assertNotFound();
});

test('live fixtures return 200 with matches in play and no markets', function () {
    $inPlay = pricedFixture(['kicks_off_at' => now()->subMinutes(30)]);
    pricedFixture();

    $response = $this->getJson(route('api.v1.fixtures.live'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inPlay->id)
        ->assertJsonMissingPath('data.0.market');
});

test('searching returns 200 with matches by team name', function () {
    $arsenal = pricedFixture(['home_team' => 'Arsenal']);
    pricedFixture(['home_team' => 'Real Madrid']);

    $response = $this->getJson(route('api.v1.fixtures.search', ['q' => 'arsen']));

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $arsenal->id);
});

test('searching with fewer than 2 characters returns 422', function () {
    $this->getJson(route('api.v1.fixtures.search', ['q' => 'a']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q' => 'The q field must be at least 2 characters.']);
});

test('a match returns 200 with its markets grouped into tabs', function () {
    $fixture = pricedFixture();
    $btts = Market::factory()->for($fixture)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    Selection::factory()->for($btts)->create(['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.72']);

    $response = $this->getJson(route('api.v1.fixtures.show', $fixture));

    $response->assertOk()
        ->assertJsonPath('data.id', $fixture->id)
        ->assertJsonPath('data.competition.slug', $fixture->competition->slug)
        ->assertJsonPath('data.tabs.0.key', 'main')
        ->assertJsonPath('data.tabs.0.markets.0.key', 'h2h')
        ->assertJsonPath('data.tabs.0.markets.0.selections.0.price', '2.10')
        ->assertJsonPath('data.tabs.1.key', 'goals')
        ->assertJsonPath('data.tabs.1.markets.0.key', 'btts')
        ->assertJsonCount(2, 'data.tabs');
});

test('a match in a competition that is not synced returns 404', function () {
    $fixture = pricedFixture();
    $fixture->competition->update(['is_synced' => false]);

    $this->getJson(route('api.v1.fixtures.show', $fixture))->assertNotFound();
});
