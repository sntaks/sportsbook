<?php

use App\Enums\AdminRole;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Reports\PeriodSummary;
use App\Filament\Pages\Reports\RevenueByDay;
use App\Filament\Pages\Reports\TaxByMonth;
use App\Filament\Pages\Reports\TopPlayers;
use App\Filament\Pages\Reports\TurnoverBreakdown;
use App\Filament\Resources\Players\PlayerResource;
use App\Filament\Widgets\KeyFigures;
use App\Filament\Widgets\TurnoverChart;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 15)->setTime(12, 0));
});

test('the reports page and the dashboard figures are for managers and finance', function (AdminRole $role, int $status) {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole($role)->create(), 'admin');

    $this->get(Reports::getUrl())->assertStatus($status);

    expect(KeyFigures::canView())->toBe($status === 200)
        ->and(TurnoverChart::canView())->toBe($status === 200);
})->with([
    'super admin' => [AdminRole::SuperAdmin, 200],
    'manager' => [AdminRole::Manager, 200],
    'finance' => [AdminRole::Finance, 200],
    'trader' => [AdminRole::Trader, 403],
    'support' => [AdminRole::Support, 403],
]);

test('the page shows the chosen days\' figures and tabs', function () {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole(AdminRole::Finance)->create(), 'admin');

    $this->get(Reports::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['Revenue', 'Turnover', 'Top players', 'Payments', 'Tax']);
});

test('the summary and daily revenue follow the range in the filters', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    $fixture = pricedFixture();
    betOn(bettor(), [[$fixture, 'away']], '250');
    settleFixture($fixture, 1, 0);

    Livewire::test(PeriodSummary::class, ['pageFilters' => ['from' => '2026-10-15', 'until' => '2026-10-15']])
        ->assertSee('KES 250.00')
        ->assertSee('100.0%');

    Livewire::test(PeriodSummary::class, ['pageFilters' => ['from' => '2026-10-01', 'until' => '2026-10-14']])
        ->assertDontSee('KES 250.00')
        ->assertSee('14 days (EAT)');

    Livewire::test(RevenueByDay::class, ['pageFilters' => ['from' => '2026-10-14', 'until' => '2026-10-15']])
        ->assertCanSeeTableRecords(['2026-10-15', '2026-10-14'])
        ->assertSee('KES 250.00');
});

test('turnover, top players and tax widgets render their rows, linking players to their page', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    $fixture = pricedFixture();
    $winner = bettor();
    betOn($winner, [[$fixture, 'home']]);
    settleFixture($fixture, 1, 0);
    $filters = ['from' => '2026-10-15', 'until' => '2026-10-15'];

    Livewire::test(TurnoverBreakdown::class, ['pageFilters' => $filters, 'dimension' => 'competition'])
        ->assertSee('Turnover by competition')
        ->assertSee($fixture->competition->name);

    Livewire::test(TopPlayers::class, ['pageFilters' => $filters, 'winners' => true])
        ->assertSee($winner->phone)
        ->assertSee('KES 110.00')
        ->assertSee(PlayerResource::getUrl('view', ['record' => $winner]));

    Livewire::test(TaxByMonth::class, ['pageFilters' => $filters])->assertSee('October 2026');
});

test('the dashboard shows key figures for today, 7 days and 30 days, and the 30-day chart', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    betOn(bettor(), [[pricedFixture(), 'home']], '40');
    $this->travel(-3)->days();
    betOn(bettor(), [[pricedFixture(), 'home']], '60');
    $this->travel(3)->days();

    Livewire::test(KeyFigures::class)
        ->assertSeeInOrder(['Turnover', 'KES 40.00', 'KES 100.00', 'KES 100.00', 'GGR']);

    Livewire::test(TurnoverChart::class)->assertOk();
});
