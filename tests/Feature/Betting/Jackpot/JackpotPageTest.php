<?php

use App\Enums\JackpotEntryStatus;
use App\Models\Fixture;
use App\Models\JackpotEntry;
use App\Wallet\Ledger;
use Livewire\Livewire;

test('guests see the open jackpot with its matches and prizes, and are asked to log in to enter', function () {
    $jackpot = openJackpot();
    $jackpot->update(['name' => 'Mega Weekend']);

    $this->get(route('jackpot'))
        ->assertOk()
        ->assertSee('Mega Weekend')
        ->assertSee($jackpot->jackpotFixtures->first()->fixture_name)
        ->assertSee('3 of 3 right')
        ->assertSee('KES 10,000.00')
        ->assertSee('Predict all 3 matches for KES 99.00.')
        ->assertSee('Log in to enter');
});

test('a guest who tries to enter is sent to log in', function () {
    $jackpot = openJackpot();

    Livewire::test('pages::sportsbook.jackpot')
        ->call('enter', jackpotPicks($jackpot, ['1', 'X', '2']))
        ->assertRedirect(route('login'));

    expect(JackpotEntry::count())->toBe(0);
});

test('a player enters with a pick for every match and sees the entry confirmed', function () {
    $jackpot = openJackpot();
    $player = bettor(100_000);
    $this->actingAs($player);

    $page = Livewire::test('pages::sportsbook.jackpot')
        ->call('enter', jackpotPicks($jackpot, ['1', 'X', '2']))
        ->assertHasNoErrors()
        ->assertDispatched('wallet-updated')
        ->assertDispatched('jackpot-entered');

    $entry = JackpotEntry::query()->sole();

    $page->assertSee('You are in!')->assertSee($entry->reference);

    expect($entry->user_id)->toBe($player->id)
        ->and($entry->picks->pluck('pick')->all())->toBe(['1', 'X', '2'])
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000 - 9_900);

    $page->call('enterAgain')->assertSee('You have 1 entry in this jackpot.');
});

test('a refused entry explains why and takes no money', function (array $picks, int $balance, string $message, bool $offersDeposit) {
    $jackpot = openJackpot();
    $player = bettor($balance);
    $this->actingAs($player);

    $page = Livewire::test('pages::sportsbook.jackpot')
        ->call('enter', jackpotPicks($jackpot, $picks))
        ->assertHasErrors(['entry' => $message]);

    $offersDeposit ? $page->assertSee('Deposit now') : $page->assertDontSee('Deposit now');

    expect(JackpotEntry::count())->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe($balance);
})->with([
    'a match left out' => [['1', 'X', ''], 100_000, 'Pick 1, X or 2 for every match.', false],
    'too little money' => [['1', 'X', '2'], 5_000, 'Your balance is KES 50.00. Deposit to enter this jackpot.', true],
]);

test('once the first match kicks off, entries are closed', function () {
    $jackpot = openJackpot();
    Fixture::query()->whereKey($jackpot->jackpotFixtures->first()->fixture_id)->update(['kicks_off_at' => now()->subMinute()]);
    $this->actingAs(bettor());

    Livewire::test('pages::sportsbook.jackpot')
        ->assertSee('Entries closed')
        ->assertDontSee('Enter for KES 99.00')
        ->call('enter', jackpotPicks($jackpot, ['1', 'X', '2']))
        ->assertHasErrors(['entry' => 'Entries for this jackpot have closed.']);

    expect(JackpotEntry::count())->toBe(0);
});

test('my bets lists the player\'s jackpot entries with how each pick did, and nobody else\'s', function () {
    $jackpot = openJackpot();
    $player = bettor();
    $mine = enterJackpot($player, $jackpot, ['1', 'X', '2']);
    $theirs = enterJackpot(bettor(), $jackpot, ['2', '2', '2']);
    $jackpot->jackpotFixtures->first()->update(['outcome' => '1']);
    $mine->update(['status' => JackpotEntryStatus::Won, 'correct_count' => 3, 'prize' => 1_000_000, 'settled_at' => now()]);
    $this->actingAs($player);

    Livewire::test('pages::sportsbook.my-bets')
        ->assertDontSee($mine->reference)
        ->call('show', 'jackpot')
        ->assertSee($mine->reference)
        ->assertSee('Result 1')
        ->assertSee('3 / 3')
        ->assertSee('KES 10,000.00')
        ->assertDontSee($theirs->reference);
});

test('my bets says so when the player has no jackpot entries', function () {
    $this->actingAs(bettor());

    Livewire::test('pages::sportsbook.my-bets', ['tab' => 'jackpot'])
        ->assertSee('No jackpot entries yet');
});
