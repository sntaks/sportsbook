<?php

use App\Enums\AdminRole;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Resources\Players\Pages\ListPlayers;
use App\Filament\Resources\Players\Pages\ViewPlayer;
use App\Filament\Resources\Players\PlayerResource;
use App\Filament\Resources\Players\RelationManagers\BetsRelationManager;
use App\Models\Admin;
use App\Models\User;
use App\Wallet\Ledger;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

test('support finds a player by phone number however it is typed', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $player = User::factory()->create(['phone' => '254712345678']);
    $other = User::factory()->create(['phone' => '254799999999']);

    Livewire::test(ListPlayers::class)
        ->searchTable('0712 345 678')
        ->assertCanSeeTableRecords([$player])
        ->assertCanNotSeeTableRecords([$other]);
});

test('accounts from before phone sign-up are listed by their email', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $legacy = User::factory()->create(['phone' => null, 'email' => 'old-account@example.com']);

    Livewire::test(ListPlayers::class)
        ->assertCanSeeTableRecords([$legacy])
        ->assertSee('old-account@example.com');
});

test('a manager suspends and reinstates a player with a reason', function () {
    $manager = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');
    $player = User::factory()->create();

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->callAction('suspend', data: ['reason' => 'Chargeback investigation'])
        ->assertNotified();

    expect($player->fresh())
        ->status->toBe(UserStatus::Suspended)
        ->suspension_reason->toBe('Chargeback investigation');

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->callAction('reinstate', data: ['reason' => 'Cleared'])
        ->assertNotified();

    expect($player->fresh()->status)->toBe(UserStatus::Active)
        ->and(Activity::query()->whereIn('event', ['player-suspended', 'player-reinstated'])->count())->toBe(2);
});

test('finance adjusts a balance through the ledger with their name and reason', function () {
    $finance = Admin::factory()->withRole(AdminRole::Finance)->create();
    $this->actingAs($finance, 'admin');
    $player = fundedPlayer(10_000);

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->callAction('adjustBalance', data: ['amount' => '-25.50', 'reason' => 'Duplicate credit reversed'])
        ->assertHasNoActionErrors();

    $entry = $player->walletTransactions()->latest('id')->first();

    expect($entry)
        ->type->toBe(WalletTransactionType::Adjustment)
        ->amount->toBe(-2_550)
        ->admin_id->toBe($finance->id)
        ->description->toBe('Duplicate credit reversed')
        ->and(app(Ledger::class)->balanceOf($player))->toBe(7_450)
        ->and(Activity::query()->where('event', 'balance-adjusted')->sole()->causer->is($finance))->toBeTrue();
});

test('an adjustment cannot take a balance below zero', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    $player = fundedPlayer(1_000);

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->callAction('adjustBalance', data: ['amount' => '-20', 'reason' => 'Test'])
        ->assertNotified('Not done');

    expect(app(Ledger::class)->balanceOf($player))->toBe(1_000);
});

test('support can look but not suspend or adjust', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $player = User::factory()->create();

    Livewire::test(ViewPlayer::class, ['record' => $player->id])
        ->assertActionHidden('suspend')
        ->assertActionHidden('adjustBalance');
});

test('a trader cannot open the players list', function () {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole(AdminRole::Trader)->create(), 'admin');

    $this->get(PlayerResource::getUrl('index'))->assertForbidden();
});

test('a player\'s page lists only their own bets', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $player = bettor();
    $theirs = betOn($player, [[pricedFixture(), 'home']]);
    $someoneElses = betOn(bettor(), [[pricedFixture(), 'home']]);

    Livewire::test(BetsRelationManager::class, ['ownerRecord' => $player, 'pageClass' => ViewPlayer::class])
        ->assertCanSeeTableRecords([$theirs])
        ->assertCanNotSeeTableRecords([$someoneElses]);
});
