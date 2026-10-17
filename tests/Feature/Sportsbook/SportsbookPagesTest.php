<?php

use App\Enums\MarketSource;
use App\Enums\MarketType;
use App\Models\Market;
use App\Models\Selection;
use Illuminate\Support\Facades\Cache;

test('the home page lists matches with their 1X2 odds and a link to every market', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea'], ['home' => '2.10', 'draw' => '3.40', 'away' => '3.50']);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSeeInOrder(['Arsenal', 'Chelsea', '2.10', '3.40', '3.50'])
        ->assertSee(route('matches.show', $fixture));
});

test('the home page switches the list column to another market', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal']);
    $btts = Market::factory()->for($fixture)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    Selection::factory()->for($btts)->create(['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.72']);
    Selection::factory()->for($btts)->create(['outcome' => 'no', 'name' => 'No', 'feed_price' => '1.92']);

    $response = $this->get(route('home', ['market' => 'btts']));

    $response->assertOk()->assertSeeInOrder(['Yes', '1.72', 'No', '1.92']);
});

test('the home page falls back to its defaults for unknown tabs and markets', function () {
    pricedFixture(['home_team' => 'Arsenal']);

    $response = $this->get(route('home', ['tab' => 'nonsense', 'market' => 'corners']));

    $response->assertOk()->assertSee('Arsenal');
});

test('a competition page lists only that competition\'s matches', function () {
    $own = pricedFixture(['home_team' => 'Arsenal']);
    pricedFixture(['home_team' => 'Real Madrid']);

    $response = $this->get(route('competitions.show', $own->competition));

    $response->assertOk()->assertSee('Arsenal')->assertDontSee('Real Madrid');
});

test('competition and match pages are not found for competitions that are not synced', function () {
    $fixture = pricedFixture();
    $fixture->competition->update(['is_synced' => false]);

    $this->get(route('competitions.show', $fixture->competition))->assertNotFound();
    $this->get(route('matches.show', $fixture))->assertNotFound();
});

test('a match page groups its markets into tabs', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);
    $btts = Market::factory()->for($fixture)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    Selection::factory()->for($btts)->create(['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.72']);

    $response = $this->get(route('matches.show', $fixture));

    $response->assertOk()
        ->assertSeeInOrder(['Main', 'Goals'])
        ->assertSee('1X2')
        ->assertDontSee('Both Teams To Score');

    $this->get(route('matches.show', [$fixture, 'tab' => 'goals']))->assertSee('Both Teams To Score');
});

test('a match page tells players when betting has closed because the match started', function () {
    $fixture = pricedFixture(['kicks_off_at' => now()->subMinutes(10)]);

    $response = $this->get(route('matches.show', $fixture));

    $response->assertOk()->assertSee('This match has started. Betting on it is closed.');
});

test('the live page lists matches in play without odds', function () {
    pricedFixture(['home_team' => 'Arsenal', 'kicks_off_at' => now()->subMinutes(30)]);

    $response = $this->get(route('live'));

    $response->assertOk()->assertSee('Arsenal')->assertSee('In play')->assertDontSee('odds-btn-price');
});

test('search finds matches by team name', function () {
    pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);

    $this->get(route('search', ['q' => 'arsen']))->assertOk()->assertSee('Chelsea');
});

test('escapes team names from the feed', function () {
    pricedFixture(['home_team' => "Wanderers <script>alert('x')</script>"]);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Wanderers &lt;script&gt;', escape: false)
        ->assertDontSee("<script>alert('x')</script>", escape: false);
});

test('the sidebar lists synced competitions with their match counts and caches only plain data', function () {
    Cache::forget('sportsbook.sidebar');
    $fixture = pricedFixture();
    $fixture->competition->update(['name' => 'Premier League']);

    $this->get(route('home'))->assertOk()->assertSeeInOrder(['Premier League', '1']);

    expect(json_encode(Cache::get('sportsbook.sidebar')))->toContain('"name":"Premier League"')
        ->and(Cache::get('sportsbook.sidebar')[0]['competitions'][0])->toBeArray();
});
