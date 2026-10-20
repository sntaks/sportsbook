<?php

use App\Enums\AdminRole;
use App\Enums\JackpotEntryStatus;
use App\Enums\JackpotStatus;
use App\Filament\Resources\Jackpots\JackpotResource;
use App\Filament\Resources\Jackpots\Pages\CreateJackpot;
use App\Filament\Resources\Jackpots\Pages\EditJackpot;
use App\Filament\Resources\Jackpots\Pages\ListJackpots;
use App\Filament\Resources\Jackpots\Pages\ViewJackpot;
use App\Filament\Resources\Jackpots\RelationManagers\EntriesRelationManager;
use App\Models\Admin;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Jackpot;
use App\Wallet\Ledger;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
    config(['sportsbook.jackpot.min_matches' => 3, 'sportsbook.jackpot.max_matches' => 5]);
    Repeater::fake();
});

/**
 * Upcoming fixtures in a synced competition, an hour apart from tomorrow.
 *
 * @return list<int>
 */
function upcomingFixtureIds(int $count): array
{
    $competition = Competition::factory()->synced()->create();

    return collect(range(1, $count))
        ->map(fn (int $hour): int => Fixture::factory()->for($competition)->create(['kicks_off_at' => now()->addDay()->addHours($count - $hour)])->id)
        ->all();
}

test('a trader drafts a jackpot in shillings and it is stored in cents with matches in kick-off order', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $fixtureIds = upcomingFixtureIds(3);

    Livewire::test(CreateJackpot::class)
        ->assertSchemaStateSet(['entry_price' => 99, 'void_limit' => 3])
        ->fillForm([
            'name' => 'Weekend Jackpot',
            'entry_price' => 50,
            'void_limit' => 1,
            'fixture_ids' => $fixtureIds,
            'prize_tiers' => [['correct' => 2, 'pool' => 1_000], ['correct' => 3, 'pool' => 100_000]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $jackpot = Jackpot::query()->sole();

    expect($jackpot)
        ->status->toBe(JackpotStatus::Draft)
        ->entry_price->toBe(5_000)
        ->prize_tiers->toBe([['correct' => 3, 'pool' => 10_000_000], ['correct' => 2, 'pool' => 100_000]])
        ->and($jackpot->jackpotFixtures->pluck('fixture_id')->all())->toBe(array_reverse($fixtureIds));
});

test('two tiers for the same number right are refused', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');

    Livewire::test(CreateJackpot::class)
        ->fillForm([
            'name' => 'Weekend Jackpot',
            'fixture_ids' => upcomingFixtureIds(3),
            'prize_tiers' => [['correct' => 3, 'pool' => 1_000], ['correct' => 3, 'pool' => 500]],
        ])
        ->call('create')
        ->assertHasFormErrors(['prize_tiers.1.correct']);

    expect(Jackpot::count())->toBe(0);
});

test('a trader changes a draft, but not a published jackpot', function () {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole(AdminRole::Trader)->create(), 'admin');
    $draft = Jackpot::factory()->withMatches(3)->create();

    Livewire::test(EditJackpot::class, ['record' => $draft->getRouteKey()])
        ->assertSchemaStateSet(['entry_price' => 99, 'prize_tiers' => [['correct' => 3, 'pool' => 10_000], ['correct' => 2, 'pool' => 1_000]]])
        ->fillForm(['entry_price' => 199])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($draft->fresh()->entry_price)->toBe(19_900);

    $this->get(JackpotResource::getUrl('edit', ['record' => openJackpot()]))->assertForbidden();
});

test('publishing opens a complete draft to entries', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $draft = Jackpot::factory()->withMatches(3)->create();

    Livewire::test(ViewJackpot::class, ['record' => $draft->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Jackpot published and taking entries');

    expect($draft->fresh())->status->toBe(JackpotStatus::Open)->published_at->not->toBeNull();
});

test('a draft that is not complete stays a draft', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $draft = Jackpot::factory()->withMatches(2)->create();

    Livewire::test(ViewJackpot::class, ['record' => $draft->getRouteKey()])
        ->callAction('publish')
        ->assertNotified('Not done');

    expect($draft->fresh()->status)->toBe(JackpotStatus::Draft);
});

test('cancelling an open jackpot refunds every entry', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    $jackpot = openJackpot();
    $entry = enterJackpot(bettor(100_000), $jackpot, ['1', 'X', '2']);

    Livewire::test(ViewJackpot::class, ['record' => $jackpot->getRouteKey()])
        ->callAction('cancel', data: ['reason' => ''])
        ->assertHasFormErrors(['reason' => 'required']);

    Livewire::test(ViewJackpot::class, ['record' => $jackpot->getRouteKey()])
        ->callAction('cancel', data: ['reason' => 'Fixture list was wrong'])
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Cancelled)
        ->and($entry->fresh()->status)->toBe(JackpotEntryStatus::Refunded)
        ->and(app(Ledger::class)->balanceOf($entry->user))->toBe(100_000);
});

test('support can look jackpots up but not change them', function () {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole(AdminRole::Support)->create(), 'admin');
    $jackpot = openJackpot();
    $entry = enterJackpot(bettor(), $jackpot, ['1', 'X', '2']);

    Livewire::test(ListJackpots::class)->assertCanSeeTableRecords([$jackpot])->assertActionHidden('create');
    Livewire::test(ViewJackpot::class, ['record' => $jackpot->getRouteKey()])->assertActionHidden('cancel');
    Livewire::test(EntriesRelationManager::class, ['ownerRecord' => $jackpot, 'pageClass' => ViewJackpot::class])
        ->assertCanSeeTableRecords([$entry])
        ->assertSee('1 X 2');
    $this->get(JackpotResource::getUrl('create'))->assertForbidden();
});

test('the view shows what each tier would pay on the results so far', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Trader)->create(), 'admin');
    $jackpot = openJackpot();
    enterJackpot(bettor(), $jackpot, ['1', 'X', '2']);
    enterJackpot(bettor(), $jackpot, ['1', 'X', '1']);

    Livewire::test(ViewJackpot::class, ['record' => $jackpot->getRouteKey()])
        ->assertSee('2 for KES 198.00')
        ->assertSee('3 right: KES 10,000.00, 0 winners, — each');
});
