<?php

use App\Betting\BetNotPlaced;
use App\Betting\PlaceBet;
use App\Betting\Settlement\CancelBet;
use App\Betting\Settlement\RecordResult;
use App\Betting\Settlement\VoidLeg;
use App\Betting\Settlement\VoidPostponedLegs;
use App\Enums\BetLegStatus;
use App\Enums\BetStatus;
use App\Enums\FixtureStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\WalletTransaction;
use App\Wallet\Ledger;
use App\Wallet\Withdrawals\RequestWithdrawal;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function result(Fixture $fixture, int $home, int $away, array $extra = []): array
{
    return app(RecordResult::class)->handle($fixture, ['home_score' => $home, 'away_score' => $away, ...$extra], FixtureResult::SOURCE_FEED);
}

test('a winning single pays stake times odds and records the payout', function () {
    $player = bettor(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    result($fixture, 2, 0);

    expect($bet->fresh())
        ->status->toBe(BetStatus::Won)
        ->payout->toBe(21_000)
        ->settled_at->not->toBeNull()
        ->and(app(Ledger::class)->balanceOf($player))->toBe(111_000);
    expect($bet->fresh()->legs->sole()->status)->toBe(BetLegStatus::Won);
});

test('a losing single pays nothing', function () {
    $player = bettor(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    result($fixture, 0, 1);

    expect($bet->fresh())->status->toBe(BetStatus::Lost)->payout->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
});

test('a multi is lost as soon as one leg loses, with other matches still to play', function () {
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn(bettor(), [[$first, 'home'], [$second, 'home']]);

    result($first, 0, 2);

    expect($bet->fresh()->status)->toBe(BetStatus::Lost)
        ->and($bet->fresh()->legs->pluck('status')->all())->toBe([BetLegStatus::Lost, BetLegStatus::Open]);
});

test('a multi waits for every leg, then pays the combined odds of the winning legs', function () {
    $player = bettor(100_000);
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn($player, [[$first, 'home'], [$second, 'draw']]);

    result($first, 1, 0);

    expect($bet->fresh()->status)->toBe(BetStatus::Open);

    result($second, 2, 2);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(71_400)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(161_400);
});

test('a void leg counts as 1.00 in a multi', function () {
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn(bettor(), [[$first, 'home'], [$second, 'home']]);

    result($first, 1, 0);
    app(RecordResult::class)->handle($second, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(21_000);
});

test('when every leg is void the stake comes back and the bet is void', function () {
    $player = bettor(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    app(RecordResult::class)->handle($fixture, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    expect($bet->fresh())->status->toBe(BetStatus::Void)->payout->toBe(10_000)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000)
        ->and($fixture->fresh()->status)->toBe(FixtureStatus::Cancelled);
    expect($player->walletTransactions()->latest('id')->first()->type)->toBe(WalletTransactionType::StakeRefund);
});

test('payouts are capped at the maximum payout', function () {
    $player = bettor(10_000_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'away']], '20000');
    config(['sportsbook.limits.max_payout' => 50_000]);

    result($fixture, 0, 1);

    expect($bet->fresh()->payout)->toBe(5_000_000);
});

test('withholding tax, when set, comes off net winnings as its own entry', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $player = bettor(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);

    result($fixture, 1, 0);

    expect($bet->fresh()->payout)->toBe(18_800)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::WinningsTax)->sole()->amount)->toBe(-2_200)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(108_800);
});

test('HT/FT legs wait for the half-time score, which settles them when entered', function () {
    $fixture = pricedFixture();
    $market = $fixture->markets()->create(['type' => 'ht_ft', 'key' => 'ht_ft', 'status' => 'open', 'source' => 'derived', 'bookmaker_count' => 10, 'priced_at' => now()]);
    $selection = $market->selections()->create(['outcome' => 'X/1', 'name' => 'Draw / Home', 'feed_price' => '5.00']);
    $bet = app(PlaceBet::class)->handle(bettor(), [slipPick($selection)], '100');

    result($fixture, 2, 1);

    expect($bet->fresh()->status)->toBe(BetStatus::Open);

    result($fixture, 2, 1, ['half_time_home_score' => 0, 'half_time_away_score' => 0]);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(50_000);
});

test('a corrected result takes back the earlier payout and settles again', function () {
    $player = bettor(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);
    result($fixture, 1, 0);

    app(RecordResult::class)->handle($fixture, ['home_score' => 1, 'away_score' => 1, 'note' => 'Late equaliser missed'], FixtureResult::SOURCE_ADMIN, Admin::factory()->create());

    expect($bet->fresh())
        ->status->toBe(BetStatus::Lost)
        ->settlement_version->toBe(1)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(90_000);
    expect(WalletTransaction::query()->where('type', WalletTransactionType::SettlementReversal)->sole()->amount)->toBe(-21_000);
    expect(Activity::query()->where('event', 'result-corrected')->sole()->properties->get('previous_result'))->toBe('1-0');
});

test('a correction can take the balance below zero, which then blocks withdrawals and bets', function () {
    $player = bettor(10_000);
    $fixture = pricedFixture();
    betOn($player, [[$fixture, 'home']]);
    result($fixture, 1, 0);
    app(Ledger::class)->post($player, WalletTransactionType::Adjustment, -21_000, 'spent-it');

    result($fixture, 0, 1);

    expect(app(Ledger::class)->balanceOf($player))->toBe(-21_000);
    expect(fn () => app(RequestWithdrawal::class)->handle($player, '50'))
        ->toThrow(ValidationException::class, 'Your balance is -KES 210.00, so there is nothing to withdraw.');
});

test('correcting a result twice to the same score pays only once', function () {
    $player = bettor(100_000);
    $fixture = pricedFixture();
    betOn($player, [[$fixture, 'home']]);

    result($fixture, 1, 0);
    result($fixture, 1, 0);
    result($fixture, 1, 0);

    expect(app(Ledger::class)->balanceOf($player))->toBe(111_000);
});

test('voiding a leg on a settled bet re-settles it at 1.00 and survives a later correction', function () {
    $player = bettor(100_000);
    $first = pricedFixture();
    $second = pricedFixture();
    $bet = betOn($player, [[$first, 'home'], [$second, 'home']]);
    result($first, 1, 0);
    result($second, 0, 1);

    app(VoidLeg::class)->handle($bet->legs()->where('fixture_id', $second->id)->sole(), 'Priced in error', Admin::factory()->create());

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(21_000);

    result($second, 0, 2);

    expect($bet->fresh()->payout)->toBe(21_000)
        ->and(Activity::query()->where('event', 'leg-voided')->exists())->toBeTrue();
});

test('cancelling an open bet returns the stake; a settled bet cannot be cancelled', function () {
    $player = bettor(100_000);
    $fixture = pricedFixture();
    $bet = betOn($player, [[$fixture, 'home']]);
    $admin = Admin::factory()->create();

    app(CancelBet::class)->handle($bet, $admin, 'Placed in error');

    expect($bet->fresh())->status->toBe(BetStatus::Cancelled)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000);

    $settled = betOn($player, [[pricedFixture(), 'home']]);
    $settled->update(['status' => BetStatus::Lost]);

    expect(fn () => app(CancelBet::class)->handle($settled, $admin, 'Too late'))->toThrow(ValidationException::class);
});

test('a leg on a match moved more than 48 hours is voided; a smaller move is not', function () {
    $moved = pricedFixture();
    $nudged = pricedFixture();
    $bet = betOn(bettor(), [[$moved, 'home'], [$nudged, 'home']]);

    $moved->update(['kicks_off_at' => $moved->kicks_off_at->addHours(49)]);
    $nudged->update(['kicks_off_at' => $nudged->kicks_off_at->addHours(3)]);

    expect(app(VoidPostponedLegs::class)->handle())->toBe(1);
    expect($bet->fresh()->legs->pluck('status')->all())->toBe([BetLegStatus::Void, BetLegStatus::Open]);
});

test('bets cannot be placed on a match that was called off', function () {
    $fixture = pricedFixture();
    $fixture->update(['status' => FixtureStatus::Cancelled]);

    expect(fn () => betOn(bettor(), [[$fixture, 'home']]))->toThrow(BetNotPlaced::class);
});
