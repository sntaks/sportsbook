<?php

use App\Betting\BetNotPlaced;
use App\Betting\PlaceBet;
use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\BetType;
use App\Enums\MarketStatus;
use App\Enums\OddsChangePolicy;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Models\Fixture;
use App\Wallet\Ledger;

/**
 * The reason PlaceBet refused, or null if it placed the bet.
 */
function refusal(Closure $place): ?string
{
    try {
        $place();
    } catch (BetNotPlaced $e) {
        return $e->reason;
    }

    return null;
}

test('places a single at the current price and takes the stake from the wallet', function () {
    $player = fundedPlayer(100_000);
    $home = selectionFor(pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']), 'home');

    $bet = app(PlaceBet::class)->handle($player, [slipPick($home)], '100');

    expect($bet)
        ->type->toBe(BetType::Single)
        ->status->toBe(BetStatus::Open)
        ->stake->toBe(10_000)
        ->total_odds->toBe('2.10')
        ->potential_payout->toBe(21_000)
        ->reference->toMatch('/^[A-HJ-NP-Z2-9]{8}$/')
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
    expect($bet->legs->sole())
        ->selection_id->toBe($home->id)
        ->fixture_name->toBe('Arsenal v Chelsea')
        ->market_name->toBe('1X2')
        ->selection_name->toBe('Arsenal')
        ->price->toBe('2.10')
        ->status->toBe(BetLegStatus::Open);
    expect($player->walletTransactions()->latest('id')->first())
        ->type->toBe(WalletTransactionType::Stake)
        ->amount->toBe(-10_000);
});

test('several picks make one multi at the combined odds', function () {
    $player = fundedPlayer(100_000);
    $picks = [
        slipPick(selectionFor(pricedFixture(), 'home')),
        slipPick(selectionFor(pricedFixture(), 'draw')),
        slipPick(selectionFor(pricedFixture(), 'away')),
    ];

    $bet = app(PlaceBet::class)->handle($player, $picks, '100');

    expect($bet)
        ->type->toBe(BetType::Multi)
        ->total_odds->toBe('24.99')
        ->bonus_percent->toBe(3)
        // KES 2,499 plus the 3% multibet bonus on the KES 2,399 profit.
        ->potential_payout->toBe(257_097)
        ->and($bet->legs)->toHaveCount(3);
});

test('the possible win is capped at the maximum payout', function () {
    config(['sportsbook.limits.max_payout' => 1_000]);
    $bet = app(PlaceBet::class)->handle(fundedPlayer(1_000_000), [slipPick(selectionFor(pricedFixture(), 'home'))], '1000');

    expect($bet->potential_payout)->toBe(100_000);
});

test('the odds-change setting decides whether a moved price stops the bet', function (OddsChangePolicy $policy, string $seen, ?string $expected) {
    $home = selectionFor(pricedFixture(), 'home');

    expect(refusal(fn () => app(PlaceBet::class)->handle(fundedPlayer(100_000), [slipPick($home, $seen)], '100', $policy)))->toBe($expected);
})->with([
    'any, price fell' => [OddsChangePolicy::Any, '2.50', null],
    'higher only, price rose' => [OddsChangePolicy::Higher, '1.90', null],
    'higher only, price fell' => [OddsChangePolicy::Higher, '2.50', BetNotPlaced::PRICE_CHANGED],
    'none, price rose' => [OddsChangePolicy::None, '1.90', BetNotPlaced::PRICE_CHANGED],
    'none, unchanged' => [OddsChangePolicy::None, '2.10', null],
]);

test('a bet accepted after a price change is placed at the new price', function () {
    $home = selectionFor(pricedFixture(), 'home');

    $bet = app(PlaceBet::class)->handle(fundedPlayer(100_000), [slipPick($home, '1.90')], '100', OddsChangePolicy::Higher);

    expect($bet->legs->sole()->price)->toBe('2.10');
});

test('the player\'s saved odds-change setting is used when none is given', function () {
    $player = fundedPlayer(100_000, ['odds_change_policy' => OddsChangePolicy::None]);
    $home = selectionFor(pricedFixture(), 'home');

    expect(refusal(fn () => app(PlaceBet::class)->handle($player, [slipPick($home, '1.90')], '100')))->toBe(BetNotPlaced::PRICE_CHANGED);
});

test('a pick that can no longer be bet on stops the bet', function (Closure $close) {
    $fixture = pricedFixture();
    $close($fixture);

    expect(refusal(fn () => app(PlaceBet::class)->handle(fundedPlayer(100_000), [slipPick(selectionFor($fixture->fresh(['matchResultMarket.selections', 'matchResultMarket.fixture']), 'home'))], '100')))
        ->toBe(BetNotPlaced::UNAVAILABLE);
    expect(Bet::count())->toBe(0);
})->with([
    'kicked off' => [fn (Fixture $fixture) => $fixture->update(['kicks_off_at' => now()->subMinute()])],
    'suspended' => [fn (Fixture $fixture) => $fixture->matchResultMarket->update(['status' => MarketStatus::Suspended])],
]);

test('two picks from the same match are refused', function () {
    $fixture = pricedFixture();

    expect(refusal(fn () => app(PlaceBet::class)->handle(fundedPlayer(100_000), [slipPick(selectionFor($fixture, 'home')), slipPick(selectionFor($fixture, 'away'))], '100')))
        ->toBe(BetNotPlaced::DUPLICATE_MATCH);
});

test('the stake must be whole shillings within the stake limits', function (string $stake) {
    expect(refusal(fn () => app(PlaceBet::class)->handle(fundedPlayer(10_000_000), [slipPick(selectionFor(pricedFixture(), 'home'))], $stake)))
        ->toBe(BetNotPlaced::STAKE);
})->with(['below the minimum' => '9', 'above the maximum' => '20001', 'cents' => '100.50', 'not a number' => 'ten']);

test('not enough money places nothing', function () {
    $player = fundedPlayer(5_000);

    expect(refusal(fn () => app(PlaceBet::class)->handle($player, [slipPick(selectionFor(pricedFixture(), 'home'))], '100')))
        ->toBe(BetNotPlaced::INSUFFICIENT_FUNDS);
    expect(Bet::count())->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(5_000);
});

test('players on a break or suspended cannot bet', function (array $attributes) {
    expect(refusal(fn () => app(PlaceBet::class)->handle(fundedPlayer(100_000, $attributes), [slipPick(selectionFor(pricedFixture(), 'home'))], '100')))
        ->toBe(BetNotPlaced::NOT_ALLOWED);
})->with([
    'on a break' => [['excluded_until' => '2099-01-01 00:00:00']],
    'suspended' => [['status' => UserStatus::Suspended]],
]);

test('submitting the same slip twice places one bet', function () {
    $player = fundedPlayer(100_000);
    $picks = [slipPick(selectionFor(pricedFixture(), 'home'))];

    $first = app(PlaceBet::class)->handle($player, $picks, '100', clientKey: 'slip-1');
    $second = app(PlaceBet::class)->handle($player, $picks, '100', clientKey: 'slip-1');

    expect($second->is($first))->toBeTrue()
        ->and(Bet::count())->toBe(1)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
});

test('an empty slip is refused', function () {
    expect(refusal(fn () => app(PlaceBet::class)->handle(fundedPlayer(100_000), [], '100')))->toBe(BetNotPlaced::EMPTY_SLIP);
});
