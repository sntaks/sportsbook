<?php

use App\Betting\Jackpot\EnterJackpot;
use App\Betting\Jackpot\JackpotEntryRefused;
use App\Enums\JackpotEntryStatus;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use App\Wallet\Ledger;

/**
 * The reason EnterJackpot refused, or null if it made the entry.
 */
function entryRefusal(Closure $enter): ?string
{
    try {
        $enter();
    } catch (JackpotEntryRefused $e) {
        return $e->reason;
    }

    return null;
}

test('an entry records a pick for every match and takes the entry price from the wallet', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();

    $entry = enterJackpot($player, $jackpot, ['1', 'x', '2']);

    expect($entry)
        ->status->toBe(JackpotEntryStatus::Open)
        ->stake->toBe(9_900)
        ->reference->toMatch('/^J[A-HJ-NP-Z2-9]{7}$/')
        ->and($entry->picks()->orderBy('jackpot_fixture_id')->pluck('pick')->all())->toBe(['1', 'X', '2'])
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_100);
    expect($player->walletTransactions()->latest('id')->first())
        ->type->toBe(WalletTransactionType::JackpotEntry)
        ->amount->toBe(-9_900)
        ->id->toBe($entry->stake_transaction_id);
});

test('a player may enter the same jackpot more than once', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();

    enterJackpot($player, $jackpot, ['1', '1', '1']);
    enterJackpot($player, $jackpot, ['2', '2', '2']);

    expect($player->jackpotEntries()->count())->toBe(2)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(80_200);
});

test('submitting the same entry key twice makes one entry and charges once', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();

    $first = enterJackpot($player, $jackpot, ['1', 'X', '2'], 'key-1');
    $second = enterJackpot($player, $jackpot, ['2', '2', '2'], 'key-1');

    expect($second->id)->toBe($first->id)
        ->and(JackpotEntry::count())->toBe(1)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_100);
});

test('every match needs a pick of 1, X or 2', function (Closure $picks) {
    $player = bettor();
    $jackpot = openJackpot();

    expect(entryRefusal(fn () => app(EnterJackpot::class)->handle($player, $jackpot, $picks($jackpot))))->toBe(JackpotEntryRefused::PICKS)
        ->and(JackpotEntry::count())->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000);
})->with([
    'a match left out' => [fn (Jackpot $jackpot) => array_slice(jackpotPicks($jackpot, ['1', 'X', '2']), 0, 2, true)],
    'not 1, X or 2' => [fn (Jackpot $jackpot) => jackpotPicks($jackpot, ['1', 'X', '3'])],
    'a match from another jackpot' => [fn (Jackpot $jackpot) => jackpotPicks($jackpot, ['1', 'X', '2']) + [openJackpot()->jackpotFixtures->first()->id => '1']],
]);

test('entries close when the first match kicks off', function () {
    $jackpot = openJackpot();
    $jackpot->jackpotFixtures->first()->fixture->update(['kicks_off_at' => now()->subMinute()]);

    expect(entryRefusal(fn () => enterJackpot(bettor(), $jackpot, ['1', 'X', '2'])))->toBe(JackpotEntryRefused::CLOSED);
});

test('a draft or settled jackpot takes no entries', function (Closure $state) {
    $jackpot = openJackpot();
    $jackpot->update($state());

    expect(entryRefusal(fn () => enterJackpot(bettor(), $jackpot, ['1', 'X', '2'])))->toBe(JackpotEntryRefused::CLOSED);
})->with([
    'draft' => [fn () => ['status' => 'draft']],
    'settled' => [fn () => ['status' => 'settled']],
    'cancelled' => [fn () => ['status' => 'cancelled']],
]);

test('a suspended or self-excluded player cannot enter', function (array $attributes) {
    $player = fundedPlayer(100_000, $attributes);

    expect(entryRefusal(fn () => enterJackpot($player, openJackpot(), ['1', 'X', '2'])))->toBe(JackpotEntryRefused::NOT_ALLOWED);
})->with([
    'suspended' => [['status' => UserStatus::Suspended]],
    'on a break' => [['excluded_until' => '2099-01-01 00:00:00']],
]);

test('an entry the balance cannot cover is refused and nothing is saved', function () {
    $player = bettor(5_000);

    expect(entryRefusal(fn () => enterJackpot($player, openJackpot(), ['1', 'X', '2'])))->toBe(JackpotEntryRefused::INSUFFICIENT_FUNDS)
        ->and(JackpotEntry::count())->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(5_000);
});
