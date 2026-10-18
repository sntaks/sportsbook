<?php

use App\Betting\Settlement\RecordResult;
use App\Enums\AdminRole;
use App\Enums\BetStatus;
use App\Enums\FixtureStatus;
use App\Filament\Resources\Fixtures\Pages\ListFixtures;
use App\Filament\Resources\Fixtures\Pages\ViewFixture;
use App\Models\Admin;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Wallet\Ledger;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

/**
 * A fixture with an open bet on the home win, kicked off three hours ago.
 */
function playedFixtureWithBet(): Fixture
{
    $fixture = pricedFixture();
    betOn(bettor(), [[$fixture, 'home']]);
    $fixture->update(['kicks_off_at' => now()->subHours(3)]);

    return $fixture;
}

function fixturePage(Fixture $fixture)
{
    return Livewire::test(ViewFixture::class, ['record' => $fixture->getRouteKey()]);
}

test('a trader enters a result and the bets on the match settle', function () {
    $trader = Admin::factory()->withRole(AdminRole::Trader)->create();
    $this->actingAs($trader, 'admin');
    $fixture = playedFixtureWithBet();
    $bet = $fixture->betLegs()->sole()->bet;

    fixturePage($fixture)
        ->callAction('enterResult', data: ['home_score' => 2, 'away_score' => 1, 'half_time_home_score' => 1, 'half_time_away_score' => 0])
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertSee('2-1 (HT 1-0)');

    expect($fixture->result()->sole())
        ->home_score->toBe(2)
        ->half_time_away_score->toBe(0)
        ->source->toBe(FixtureResult::SOURCE_ADMIN)
        ->recorded_by_admin_id->toBe($trader->id)
        ->and($bet->fresh()->status)->toBe(BetStatus::Won);
});

test('a half-time score cannot be higher than the full-time score', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $fixture = playedFixtureWithBet();

    fixturePage($fixture)
        ->callAction('enterResult', data: ['home_score' => 1, 'away_score' => 0, 'half_time_home_score' => 2, 'half_time_away_score' => 0])
        ->assertHasFormErrors(['half_time_home_score' => 'lte']);

    expect($fixture->result()->exists())->toBeFalse();
});

test('correcting a result needs a note and resettles the bets', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $fixture = playedFixtureWithBet();
    $bet = $fixture->betLegs()->sole()->bet;
    $player = $bet->user;

    fixturePage($fixture)->callAction('enterResult', data: ['home_score' => 2, 'away_score' => 0]);
    expect($bet->fresh()->status)->toBe(BetStatus::Won);

    fixturePage($fixture)
        ->callAction('enterResult', data: ['home_score' => 0, 'away_score' => 2, 'note' => ''])
        ->assertHasFormErrors(['note' => 'required']);

    fixturePage($fixture)
        ->callAction('enterResult', data: ['home_score' => 0, 'away_score' => 2, 'note' => 'Feed had the teams swapped'])
        ->assertHasNoFormErrors();

    expect($bet->fresh()->status)->toBe(BetStatus::Lost)
        ->and($fixture->result()->sole()->note)->toBe('Feed had the teams swapped')
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
});

test('voiding a match refunds singles and calls the match off', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    $fixture = playedFixtureWithBet();
    $bet = $fixture->betLegs()->sole()->bet;

    fixturePage($fixture)
        ->callAction('voidMatch', data: ['reason' => 'Abandoned after 20 minutes, floodlight failure'])
        ->assertHasNoFormErrors()
        ->assertActionHidden('voidMatch')
        ->assertSee('Called off');

    expect($fixture->fresh()->status)->toBe(FixtureStatus::Cancelled)
        ->and($fixture->result()->sole()->is_void)->toBeTrue()
        ->and($bet->fresh()->status)->toBe(BetStatus::Void)
        ->and(app(Ledger::class)->balanceOf($bet->user))->toBe(100_000);
});

test('results can only be entered once the match has kicked off', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');

    fixturePage(pricedFixture())->assertActionHidden('enterResult');
});

test('support and finance cannot enter results or void matches', function (AdminRole $role) {
    $this->actingAs(Admin::factory()->withRole($role)->create(), 'admin');
    $fixture = playedFixtureWithBet();

    fixturePage($fixture)
        ->assertActionHidden('enterResult')
        ->assertActionHidden('voidMatch');
})->with([
    'support' => [AdminRole::Support],
    'finance' => [AdminRole::Finance],
]);

test('the needs a result tab lists played matches with open bets and no result', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $waiting = playedFixtureWithBet();
    $settled = playedFixtureWithBet();
    $noBets = pricedFixture(['kicks_off_at' => now()->subHours(3)]);
    $tooSoon = pricedFixture();
    betOn(bettor(), [[$tooSoon, 'home']]);
    $tooSoon->update(['kicks_off_at' => now()->subHour()]);
    app(RecordResult::class)->handle($settled, ['home_score' => 1, 'away_score' => 1], FixtureResult::SOURCE_FEED);

    Livewire::test(ListFixtures::class)
        ->set('activeTab', 'needs_result')
        ->assertCanSeeTableRecords([$waiting])
        ->assertCanNotSeeTableRecords([$settled, $noBets, $tooSoon]);
});
