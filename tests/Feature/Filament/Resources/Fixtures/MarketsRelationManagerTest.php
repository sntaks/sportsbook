<?php

use App\Enums\AdminRole;
use App\Enums\MarketStatus;
use App\Filament\Resources\Fixtures\Pages\ViewFixture;
use App\Filament\Resources\Fixtures\RelationManagers\MarketsRelationManager;
use App\Models\Admin;
use App\Models\Market;
use App\Models\Selection;
use Database\Seeders\AdminRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

function marketsTable(Market $market)
{
    return Livewire::test(MarketsRelationManager::class, ['ownerRecord' => $market->fixture, 'pageClass' => ViewFixture::class]);
}

test('a trader overrides a price from the fixture page', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $market = Market::factory()->create();
    $home = Selection::factory()->for($market)->create(['feed_price' => '1.88']);

    marketsTable($market)
        ->callAction(TestAction::make('overridePrices')->table($market), data: [
            'prices' => [$home->id => '2.10'],
            'reason' => 'Key striker ruled out',
        ])
        ->assertHasNoFormErrors();

    expect($home->fresh()->price())->toBe('2.10');
});

test('an override needs a reason', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $market = Market::factory()->create();
    $home = Selection::factory()->for($market)->create(['feed_price' => '1.88']);

    marketsTable($market)
        ->callAction(TestAction::make('overridePrices')->table($market), data: ['prices' => [$home->id => '2.10'], 'reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    expect($home->fresh()->override_price)->toBeNull();
});

test('a trader suspends a market from the fixture page', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $market = Market::factory()->create();

    marketsTable($market)
        ->callAction(TestAction::make('suspend')->table($market), data: ['reason' => 'Suspicious betting'])
        ->assertHasNoFormErrors();

    expect($market->fresh()->status)->toBe(MarketStatus::Suspended);
});

test('support staff can see markets and price history but not trade them', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $market = Market::factory()->create();

    marketsTable($market)
        ->assertActionHidden(TestAction::make('suspend')->table($market))
        ->assertActionHidden(TestAction::make('reopen')->table($market))
        ->assertActionHidden(TestAction::make('overridePrices')->table($market))
        ->assertActionVisible(TestAction::make('history')->table($market));
});
