<?php

use App\Betting\Settlement\RecordResult;
use App\Betting\Settlement\VoidLeg;
use App\Enums\BetStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\FixtureResult;
use App\Models\WalletTransaction;
use App\Promotions\MultibetBonus;
use App\Wallet\Ledger;
use Livewire\Livewire;

test('the bonus tier is the highest one the qualifying legs reach', function (int $legs, int $percent) {
    expect(MultibetBonus::percentForLegs($legs))->toBe($percent);
})->with([
    [2, 0], [3, 3], [4, 5], [5, 7], [6, 10], [7, 10], [8, 15], [9, 15],
    [10, 20], [14, 20], [15, 35], [19, 35], [20, 50], [30, 50],
]);

test('only legs priced 1.30 or more count towards the bonus', function () {
    expect(MultibetBonus::qualifyingLegs(['1.29', '1.30', '2.00', '1.05']))->toBe(2);
});

test('a winning multi pays the bonus on its profit as its own entry, taxed with the winnings', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $player = bettor(100_000);
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture()];
    $bet = betOn($player, array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));

    expect($bet)->bonus_percent->toBe(3)->potential_payout->toBe(95_078);

    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    // KES 926 back at 9.26; 3% of the KES 826 profit is KES 24.78; 20% tax on KES 850.78 is KES 170.16.
    expect($bet->fresh())->status->toBe(BetStatus::Won)->bonus->toBe(2_478)->payout->toBe(78_062)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::MultibetBonus)->sole())
        ->amount->toBe(2_478)
        ->description->toBe("Bet {$bet->reference}: multibet bonus")
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::WinningsTax)->sole()->amount)->toBe(-17_016)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(168_062);
});

test('a void leg can drop a multi to a lower bonus tier', function () {
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture(), pricedFixture()];
    $bet = betOn(bettor(), array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));

    expect($bet->bonus_percent)->toBe(5);

    app(RecordResult::class)->handle($fixtures[3], ['is_void' => true], FixtureResult::SOURCE_ADMIN);
    foreach (array_slice($fixtures, 0, 3) as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    expect($bet->fresh())->status->toBe(BetStatus::Won)->bonus->toBe(2_478)->payout->toBe(95_078);
});

test('a leg priced under 1.30 does not count towards the bonus', function () {
    $short = pricedFixture([], ['home' => '1.25', 'draw' => '5.00', 'away' => '9.00']);
    $fixtures = [$short, pricedFixture(), pricedFixture()];
    $bet = betOn(bettor(), array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));

    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    expect($bet->fresh())->bonus_percent->toBe(0)->bonus->toBe(0)->payout->toBe(55_100)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::MultibetBonus)->exists())->toBeFalse();
});

test('the bonus never takes the payout past the maximum payout', function () {
    config(['sportsbook.limits.max_payout' => 940]);
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture()];
    $bet = betOn(bettor(), array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));

    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    expect($bet->fresh())->bonus->toBe(1_400)->payout->toBe(94_000)->potential_payout->toBe(94_000);
});

test('a corrected result takes the bonus back with the winnings', function () {
    $player = bettor(100_000);
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture()];
    $bet = betOn($player, array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));
    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    settleFixture($fixtures[0], 0, 1, Admin::factory()->create());

    expect($bet->fresh())->status->toBe(BetStatus::Lost)->bonus->toBeNull()
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::SettlementReversal)->sole()->amount)->toBe(-95_078)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
});

test('voiding a leg on a settled multi re-settles its bonus at the lower tier', function () {
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture(), pricedFixture()];
    $bet = betOn(bettor(), array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));
    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    app(VoidLeg::class)->handle($bet->legs()->where('fixture_id', $fixtures[3]->id)->sole(), 'Priced in error', Admin::factory()->create());

    expect($bet->fresh())->bonus->toBe(2_478)->payout->toBe(95_078);
});

test('the bet slip shows the bonus and how many more picks reach the next tier', function () {
    $selections = [selectionFor(pricedFixture()), selectionFor(pricedFixture()), selectionFor(pricedFixture())];

    Livewire::test('sportsbook.slip')
        ->call('syncPicks', array_map(fn ($selection): array => slipPick($selection), array_slice($selections, 0, 2)))
        ->assertDontSee('Multibet bonus')
        ->assertSee('Add 1 more pick at odds 1.30+ for a 3% bonus.')
        ->call('syncPicks', array_map(fn ($selection): array => slipPick($selection), $selections))
        ->set('stake', '100')
        ->assertSee('Multibet bonus +3%')
        ->assertSee('KES 24.78')
        ->assertSee('KES 950.78')
        ->assertSee('Add 1 more pick at odds 1.30+ for a 5% bonus.');
});
