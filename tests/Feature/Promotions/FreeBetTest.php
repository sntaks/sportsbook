<?php

use App\Betting\BetNotPlaced;
use App\Betting\Exposure;
use App\Betting\PlaceBet;
use App\Betting\Settlement\CancelBet;
use App\Betting\Settlement\RecordResult;
use App\Enums\BetStatus;
use App\Enums\FreeBetSource;
use App\Enums\FreeBetStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Bet;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\FreeBet;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Wallet\Ledger;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * @param  list<array{0: Fixture, 1: string}>  $picks
 */
function betWithFreeBet(User $player, FreeBet $freeBet, array $picks): Bet
{
    return app(PlaceBet::class)->handle($player, array_map(fn (array $pick): array => slipPick(selectionFor($pick[0], $pick[1])), $picks), null, freeBetId: $freeBet->id);
}

/**
 * The reason PlaceBet refused, or null if it placed the bet.
 */
function freeBetRefusal(Closure $place): ?string
{
    try {
        $place();
    } catch (BetNotPlaced $e) {
        return $e->reason;
    }

    return null;
}

test('a free bet is the whole stake, takes nothing from the wallet and can be used once', function () {
    $player = bettor(50_000);
    $freeBet = freeBetFor($player);
    $fixture = pricedFixture();

    $bet = betWithFreeBet($player, $freeBet, [[$fixture, 'home']]);

    expect($bet)
        ->stake->toBe(10_000)
        ->free_bet_id->toBe($freeBet->id)
        ->stake_transaction_id->toBeNull()
        // KES 210 back at 2.10, less the KES 100 stake that was never the player's.
        ->potential_payout->toBe(11_000)
        ->and($freeBet->fresh())->status->toBe(FreeBetStatus::Used)->used_at->not->toBeNull()
        ->and(app(Ledger::class)->balanceOf($player))->toBe(50_000)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Stake)->exists())->toBeFalse();
    expect(freeBetRefusal(fn () => betWithFreeBet($player, $freeBet, [[pricedFixture(), 'home']])))->toBe(BetNotPlaced::FREE_BET_UNAVAILABLE);
});

test('a free bet of another player, past its expiry or voided cannot be used', function () {
    $player = bettor();
    $fixture = pricedFixture();
    $unusable = [
        freeBetFor(User::factory()->create()),
        freeBetFor($player, ['expires_at' => now()->subMinute()]),
        freeBetFor($player, ['status' => FreeBetStatus::Void]),
    ];

    foreach ($unusable as $freeBet) {
        expect(freeBetRefusal(fn () => betWithFreeBet($player, $freeBet, [[$fixture, 'home']])))->toBe(BetNotPlaced::FREE_BET_UNAVAILABLE);
    }

    expect(Bet::query()->exists())->toBeFalse();
});

test('a slip below the free bet minimum odds is refused and the free bet stays available', function () {
    $player = bettor();
    $freeBet = freeBetFor($player, ['min_odds' => '2.50']);

    expect(freeBetRefusal(fn () => betWithFreeBet($player, $freeBet, [[pricedFixture(), 'home']])))->toBe(BetNotPlaced::FREE_BET_MIN_ODDS)
        ->and($freeBet->fresh()->status)->toBe(FreeBetStatus::Available)
        ->and(betWithFreeBet($player, $freeBet, [[pricedFixture(), 'away']])->total_odds)->toBe('3.50');
});

test('a free bet multi gets no multibet bonus', function () {
    $player = bettor();

    $bet = betWithFreeBet($player, freeBetFor($player), [[pricedFixture(), 'home'], [pricedFixture(), 'draw'], [pricedFixture(), 'away']]);

    expect($bet)->bonus_percent->toBe(0)->potential_payout->toBe(239_900);
});

test('a winning free bet pays only the winnings above the stake, taxed like any winnings', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $player = bettor(50_000);
    $fixture = pricedFixture();
    $bet = betWithFreeBet($player, freeBetFor($player), [[$fixture, 'home']]);

    settleFixture($fixture, 2, 0);

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(8_800)
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::Winnings)->sole())
        ->amount->toBe(11_000)
        ->description->toBe("Free bet {$bet->reference} won")
        ->and(WalletTransaction::query()->where('type', WalletTransactionType::WinningsTax)->sole()->amount)->toBe(-2_200)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(58_800);
});

test('a losing free bet pays nothing', function () {
    $player = bettor(50_000);
    $fixture = pricedFixture();
    $bet = betWithFreeBet($player, freeBetFor($player), [[$fixture, 'home']]);

    settleFixture($fixture, 0, 1);

    expect($bet->fresh())->status->toBe(BetStatus::Lost)->payout->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(50_000);
});

test('a void free bet gets a replacement free bet, not cash', function () {
    $player = bettor(50_000);
    $fixture = pricedFixture();
    $freeBet = freeBetFor($player, ['min_odds' => '1.80']);
    $bet = betWithFreeBet($player, $freeBet, [[$fixture, 'home']]);

    app(RecordResult::class)->handle($fixture, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    expect($bet->fresh())->status->toBe(BetStatus::Void)->payout->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(50_000)
        ->and($player->freeBets()->where('source', FreeBetSource::Replacement)->sole())
        ->amount->toBe(10_000)
        ->min_odds->toBe('1.80')
        ->replaces_bet_id->toBe($bet->id)
        ->status->toBe(FreeBetStatus::Available)
        ->expires_at->toDateString()->toBe(now()->addDays(7)->toDateString());
});

test('cancelling a free bet gives a replacement free bet, not cash', function () {
    $player = bettor(50_000);
    $bet = betWithFreeBet($player, freeBetFor($player), [[pricedFixture(), 'home']]);

    app(CancelBet::class)->handle($bet, Admin::factory()->create(), 'Placed in error');

    expect($bet->fresh())->status->toBe(BetStatus::Cancelled)->payout->toBe(0)->payout_transaction_id->toBeNull()
        ->and(app(Ledger::class)->balanceOf($player))->toBe(50_000)
        ->and($player->freeBets()->where('replaces_bet_id', $bet->id)->sole()->isUsable())->toBeTrue();
});

test('a corrected result takes back an unused replacement free bet', function () {
    $player = bettor(50_000);
    $fixture = pricedFixture();
    $bet = betWithFreeBet($player, freeBetFor($player), [[$fixture, 'home']]);
    app(RecordResult::class)->handle($fixture, ['is_void' => true], FixtureResult::SOURCE_ADMIN);

    settleFixture($fixture, 1, 0, Admin::factory()->create());

    expect($bet->fresh())->status->toBe(BetStatus::Won)->payout->toBe(11_000)
        ->and($player->freeBets()->where('source', FreeBetSource::Replacement)->sole())
        ->status->toBe(FreeBetStatus::Void)
        ->void_reason->toBe('Its bet was reopened by a corrected result.');
});

test('a replacement free bet already bet with stays the player\'s, and the activity log says so', function () {
    $player = bettor(50_000);
    $fixture = pricedFixture();
    $bet = betWithFreeBet($player, freeBetFor($player), [[$fixture, 'home']]);
    app(RecordResult::class)->handle($fixture, ['is_void' => true], FixtureResult::SOURCE_ADMIN);
    $replacement = $player->freeBets()->where('source', FreeBetSource::Replacement)->sole();
    betWithFreeBet($player, $replacement, [[pricedFixture(), 'home']]);

    settleFixture($fixture, 0, 1, Admin::factory()->create());

    expect($bet->fresh()->status)->toBe(BetStatus::Lost)
        ->and($replacement->fresh()->status)->toBe(FreeBetStatus::Used)
        ->and(Activity::query()->where('event', 'replacement-free-bet-kept')->sole()->properties->get('free_bet_id'))->toBe($replacement->id);
});

test('match exposure counts only cash stakes', function () {
    $player = bettor();
    $fixture = pricedFixture();
    betOn($player, [[$fixture, 'home']]);
    betWithFreeBet($player, freeBetFor($player), [[$fixture, 'home']]);

    $match = app(Exposure::class)->byMatch()[0];

    expect($match)->bets->toBe(2)->stakes->toBe(10_000)->worst_payout->toBe(32_000);
});

test('the bet slip offers usable free bets and places a free bet with one', function () {
    $player = bettor(50_000);
    $this->actingAs($player);
    $freeBet = freeBetFor($player);
    freeBetFor($player, ['expires_at' => now()->subMinute(), 'amount' => 77_700]);
    $selection = selectionFor(pricedFixture());

    $slip = Livewire::test('sportsbook.slip')
        ->call('syncPicks', [slipPick($selection)])
        ->assertSee('Pay with cash')
        ->assertSee('KES 100.00 free bet')
        ->assertDontSee('KES 777.00 free bet')
        ->set('freeBetId', (string) $freeBet->id)
        ->assertSee('If it wins you get the winnings above the stake.')
        ->assertSee('KES 110.00')
        ->assertSee('Place free bet')
        ->call('placeBet')
        ->assertHasNoErrors()
        ->assertSet('freeBetId', '');

    expect(Bet::sole()->free_bet_id)->toBe($freeBet->id)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(50_000);
    $slip->assertDontSee('Pay with cash');
});

test('the wallet page lists the player\'s free bets', function () {
    $player = User::factory()->create();
    freeBetFor($player, ['amount' => 25_000]);
    freeBetFor($player, ['amount' => 5_000, 'expires_at' => now()->subDay()]);
    freeBetFor(User::factory()->create(), ['amount' => 99_900]);

    $this->actingAs($player)->get(route('wallet'))
        ->assertOk()
        ->assertSee('Free bets')
        ->assertSee('KES 250.00')
        ->assertSee('Expired')
        ->assertDontSee('KES 999.00');
});
