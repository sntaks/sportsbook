<?php

use App\Enums\MarketSource;
use App\Enums\MarketType;
use App\Models\Market;
use App\Models\Selection;
use Livewire\Livewire;

test('the match panel shows a match\'s markets by tab', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);
    $btts = Market::factory()->for($fixture)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived]);
    Selection::factory()->for($btts)->create(['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.72']);

    Livewire::test('sportsbook.match-panel')
        ->call('show', $fixture->id)
        ->assertSet('fixtureId', $fixture->id)
        ->assertSeeInOrder(['Arsenal', 'Chelsea', 'Main', 'Goals', '1X2'])
        ->assertDontSee('Both Teams To Score')
        ->set('tab', 'goals')
        ->assertSee('Both Teams To Score')
        ->set('tab', 'not-a-tab')
        ->assertSet('tab', 'main');
});

test('the match panel opens each new match on its main tab', function () {
    $first = pricedFixture();
    $second = pricedFixture(['home_team' => 'Everton']);

    Livewire::test('sportsbook.match-panel')
        ->call('show', $first->id)
        ->set('tab', 'goals')
        ->call('show', $second->id)
        ->assertSet('tab', 'main')
        ->assertSee('Everton');
});

test('the match panel shows nothing for matches that do not exist or whose competition is not synced', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal']);
    $fixture->competition->update(['is_synced' => false]);

    Livewire::test('sportsbook.match-panel')
        ->call('show', $fixture->id)
        ->assertSet('fixtureId', null)
        ->assertSee('Match not available')
        ->assertDontSee('Arsenal')
        ->call('show', $fixture->id + 1000)
        ->assertSet('fixtureId', null);
});

test('match lists keep the match page link for new tabs and shared links, and the layout has the match panel', function () {
    $fixture = pricedFixture();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('matches.show', $fixture))
        ->assertSeeLivewire('sportsbook.match-panel');
});

test('the home page shows the first 20 matches, then more as the player scrolls, and starts again on another tab', function () {
    foreach (range(1, 25) as $i) {
        pricedFixture(['kicks_off_at' => now()->addHours($i)]);
    }

    $home = Livewire::test('pages::sportsbook.home');

    expect(substr_count($home->html(), 'data-match='))->toBe(20);
    $home->assertSeeHtml('data-test="more-matches"');

    $home->call('showMore');

    expect(substr_count($home->html(), 'data-match='))->toBe(25);
    $home->assertDontSeeHtml('data-test="more-matches"')
        ->set('tab', 'upcoming')
        ->assertSet('shown', 20);
});
