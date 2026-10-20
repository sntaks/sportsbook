<?php

use App\Betting\PlaceBet;
use App\Enums\AdminRole;
use App\Enums\FreeBetSource;
use App\Enums\FreeBetStatus;
use App\Filament\Resources\Bets\Pages\ViewBet;
use App\Filament\Resources\Players\Pages\ViewPlayer;
use App\Filament\Resources\Players\RelationManagers\FreeBetsRelationManager;
use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

test('finance gives a free bet with their name and reason', function () {
    $finance = Admin::factory()->withRole(AdminRole::Finance)->create();
    $this->actingAs($finance, 'admin');
    $player = User::factory()->create();

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->callAction('grantFreeBet', data: ['amount' => '250', 'min_odds' => '2.00', 'days' => '3', 'note' => 'Goodwill after an outage'])
        ->assertHasNoActionErrors()
        ->assertNotified('Free bet given');

    expect($player->freeBets()->sole())
        ->amount->toBe(25_000)
        ->min_odds->toBe('2.00')
        ->status->toBe(FreeBetStatus::Available)
        ->source->toBe(FreeBetSource::Admin)
        ->admin_id->toBe($finance->id)
        ->note->toBe('Goodwill after an outage')
        ->expires_at->toDateString()->toBe(now()->addDays(3)->toDateString())
        ->and(Activity::query()->where('event', 'free-bet-given')->sole()->causer->is($finance))->toBeTrue();
});

test('a free bet must be within the stake limits', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    $player = User::factory()->create();

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->callAction('grantFreeBet', data: ['amount' => '5', 'min_odds' => '1.50', 'days' => '7', 'note' => 'Too small'])
        ->assertHasActionErrors(['amount']);

    expect($player->freeBets()->exists())->toBeFalse();
});

test('support can see a player but not give free bets', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');

    Livewire::test(ViewPlayer::class, ['record' => User::factory()->create()->id])->assertActionHidden('grantFreeBet');
});

test('the player\'s page lists only their own free bets', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $player = User::factory()->create();
    $theirs = freeBetFor($player);
    $someoneElses = freeBetFor(User::factory()->create());

    Livewire::test(FreeBetsRelationManager::class, ['ownerRecord' => $player, 'pageClass' => ViewPlayer::class])
        ->assertCanSeeTableRecords([$theirs])
        ->assertCanNotSeeTableRecords([$someoneElses])
        ->assertTableActionHidden('void', $theirs);
});

test('a manager voids an unused free bet with a reason; a used one has no void action', function () {
    $manager = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');
    $player = User::factory()->create();
    $unused = freeBetFor($player);
    $used = freeBetFor($player, ['status' => FreeBetStatus::Used]);

    Livewire::test(FreeBetsRelationManager::class, ['ownerRecord' => $player, 'pageClass' => ViewPlayer::class])
        ->assertTableActionHidden('void', $used)
        ->callTableAction('void', $unused, data: ['reason' => 'Given to the wrong player'])
        ->assertNotified('Free bet voided');

    expect($unused->fresh())->status->toBe(FreeBetStatus::Void)->void_reason->toBe('Given to the wrong player')
        ->and(Activity::query()->where('event', 'free-bet-voided')->sole()->causer->is($manager))->toBeTrue();
});

test('cancelling a free bet from the admin explains that the player gets a new free bet', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $player = bettor();
    $bet = app(PlaceBet::class)->handle($player, [slipPick(selectionFor(pricedFixture()))], null, freeBetId: freeBetFor($player)->id);

    Livewire::test(ViewBet::class, ['record' => $bet->getRouteKey()])
        ->assertSee('Free bet: no money left the wallet')
        ->callAction('cancel', data: ['reason' => 'Placed in error'])
        ->assertNotified('Bet cancelled; the player has a new free bet');

    expect($player->freeBets()->where('replaces_bet_id', $bet->id)->exists())->toBeTrue();
});
