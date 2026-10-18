<?php

use App\Enums\AdminRole;
use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Filament\Resources\Bets\BetResource;
use App\Filament\Resources\Bets\Pages\ListBets;
use App\Filament\Resources\Bets\Pages\ViewBet;
use App\Filament\Resources\Bets\RelationManagers\LegsRelationManager;
use App\Models\Admin;
use App\Models\Bet;
use App\Wallet\Ledger;
use Database\Seeders\AdminRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

function legsTable(Bet $bet)
{
    return Livewire::test(LegsRelationManager::class, ['ownerRecord' => $bet, 'pageClass' => ViewBet::class]);
}

test('support finds a bet by its reference or the player\'s phone number', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $bet = betOn(bettor(), [[pricedFixture(), 'home']]);
    $other = betOn(bettor(), [[pricedFixture(), 'away']]);
    // One digit in the reference, which every player's phone contains too.
    $bet->update(['reference' => 'ABCDEFG7']);

    Livewire::test(ListBets::class)
        ->searchTable($bet->reference)
        ->assertCanSeeTableRecords([$bet])
        ->assertCanNotSeeTableRecords([$other]);

    Livewire::test(ListBets::class)
        ->searchTable('0'.substr($bet->user->phone, 3))
        ->assertCanSeeTableRecords([$bet])
        ->assertCanNotSeeTableRecords([$other]);
});

test('a trader cancels a bet placed in error and the stake goes back', function () {
    $trader = Admin::factory()->withRole(AdminRole::Trader)->create();
    $this->actingAs($trader, 'admin');
    $bet = betOn(bettor(), [[pricedFixture(), 'home']]);

    Livewire::test(ViewBet::class, ['record' => $bet->getRouteKey()])
        ->callAction('cancel', data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    Livewire::test(ViewBet::class, ['record' => $bet->getRouteKey()])
        ->callAction('cancel', data: ['reason' => 'Priced at the wrong line'])
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertActionHidden('cancel')
        ->assertSee('Cancelled');

    expect($bet->fresh()->status)->toBe(BetStatus::Cancelled)
        ->and(app(Ledger::class)->balanceOf($bet->user))->toBe(100_000);
});

test('a trader voids one leg of a multi and the rest of the bet stays open', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $bet = betOn(bettor(), [[pricedFixture(), 'home'], [pricedFixture(), 'draw']]);
    $leg = $bet->legs->first();

    legsTable($bet)
        ->callAction(TestAction::make('voidLeg')->table($leg), data: ['reason' => 'Player was sent the wrong price'])
        ->assertHasNoFormErrors()
        ->assertRedirect(BetResource::getUrl('view', ['record' => $bet]));

    expect($leg->fresh())
        ->status->toBe(BetLegStatus::Void)
        ->void_reason->toBe('Player was sent the wrong price')
        ->and($bet->fresh()->status)->toBe(BetStatus::Open);

    legsTable($bet)->assertActionHidden(TestAction::make('voidLeg')->table($leg->fresh()));
});

test('support and finance can look at bets but not cancel them or void legs', function (AdminRole $role) {
    $this->actingAs(Admin::factory()->withRole($role)->create(), 'admin');
    $bet = betOn(bettor(), [[pricedFixture(), 'home']]);
    $leg = $bet->legs->sole();

    Livewire::test(ViewBet::class, ['record' => $bet->getRouteKey()])
        ->assertSee($bet->reference)
        ->assertActionHidden('cancel');

    legsTable($bet)->assertActionHidden(TestAction::make('voidLeg')->table($leg));
})->with([
    'support' => [AdminRole::Support],
    'finance' => [AdminRole::Finance],
]);

test('an admin without a role cannot open the bets list', function () {
    $this->actingAs(Admin::factory()->withAppAuthentication()->create(), 'admin');

    $this->get(BetResource::getUrl('index'))->assertForbidden();
});
