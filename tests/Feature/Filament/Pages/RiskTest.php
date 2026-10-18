<?php

use App\Betting\Exposure;
use App\Betting\Settlement\CancelBet;
use App\Betting\Settlement\RecordResult;
use App\Enums\AdminRole;
use App\Filament\Pages\Risk;
use App\Filament\Pages\Risk\MatchExposure;
use App\Models\Admin;
use App\Models\FixtureResult;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

test('exposure totals open bets per match and pick, riskiest match first', function () {
    $fixture = pricedFixture();
    $other = pricedFixture();
    betOn(bettor(), [[$fixture, 'home']]);
    $home = betOn(bettor(), [[$fixture, 'home']]);
    betOn(bettor(), [[$fixture, 'draw']], '50');
    betOn(bettor(), [[$other, 'away']], '10');
    $marketName = $home->legs->sole()->market_name;

    $matches = app(Exposure::class)->byMatch();

    expect($matches)->toHaveCount(2)
        ->and($matches[0])->toMatchArray([
            'fixture_id' => $fixture->id,
            'match' => $fixture->name(),
            'bets' => 3,
            'stakes' => 25_000,
            'worst_pick' => "{$marketName}: {$fixture->home_team}",
            'worst_payout' => 42_000,
        ])
        ->and($matches[0]['picks'][1])->toBe(['pick' => "{$marketName}: Draw", 'bets' => 1, 'stakes' => 5_000, 'payout' => 17_000])
        ->and($matches[1]['fixture_id'])->toBe($other->id);
});

test('exposure leaves out settled matches and cancelled bets', function () {
    $admin = Admin::factory()->create();
    $settled = pricedFixture();
    betOn(bettor(), [[$settled, 'home']]);
    app(RecordResult::class)->handle($settled, ['home_score' => 1, 'away_score' => 0], FixtureResult::SOURCE_FEED);
    $cancelled = betOn(bettor(), [[pricedFixture(), 'home']]);
    app(CancelBet::class)->handle($cancelled, $admin, 'Placed in error');

    expect(app(Exposure::class)->byMatch())->toBe([]);
});

test('the risk widget shows each match with its worst case', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $fixture = pricedFixture();
    betOn(bettor(), [[$fixture, 'home']]);

    Livewire::test(MatchExposure::class)
        ->assertSee($fixture->name())
        ->assertSee('KES 210.00');
});

test('the risk page is for admins who can view bets', function (?AdminRole $role, int $status) {
    $factory = Admin::factory()->withAppAuthentication();
    $this->actingAs(($role === null ? $factory : $factory->withRole($role))->create(), 'admin');

    $this->get(Risk::getUrl())->assertStatus($status);
})->with([
    'trader' => [AdminRole::Trader, 200],
    'support' => [AdminRole::Support, 200],
    'no role' => [null, 403],
]);
