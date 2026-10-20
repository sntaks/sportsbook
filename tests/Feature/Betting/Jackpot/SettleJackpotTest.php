<?php

use App\Betting\Jackpot\CancelJackpot;
use App\Betting\Jackpot\SettleJackpot;
use App\Betting\Jackpot\VoidPostponedJackpotMatches;
use App\Betting\Settlement\RecordResult;
use App\Enums\JackpotEntryStatus;
use App\Enums\JackpotStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\FixtureResult;
use App\Models\Jackpot;
use App\Wallet\Ledger;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * Record full-time scores for the jackpot's matches in order, e.g. [[1, 0], [2, 2], [0, 1]] for 1, X, 2. Null
 * voids the match.
 *
 * @param  list<array{0: int, 1: int}|null>  $scores
 */
function jackpotResults(Jackpot $jackpot, array $scores): void
{
    foreach ($jackpot->jackpotFixtures()->with('fixture')->get()->values() as $index => $match) {
        if (! array_key_exists($index, $scores)) {
            continue;
        }

        $score = $scores[$index];

        app(RecordResult::class)->handle(
            $match->fixture,
            $score === null ? ['is_void' => true] : ['home_score' => $score[0], 'away_score' => $score[1]],
            FixtureResult::SOURCE_FEED,
        );
    }
}

test('the jackpot settles once every match has a result, paying each tier its pool', function () {
    $jackpot = openJackpot();
    $allRight = bettor(100_000);
    $twoRight = bettor(100_000);
    $oneRight = bettor(100_000);
    $winner = enterJackpot($allRight, $jackpot, ['1', 'X', '2']);
    $runnerUp = enterJackpot($twoRight, $jackpot, ['1', 'X', '1']);
    $loser = enterJackpot($oneRight, $jackpot, ['1', '1', '1']);

    jackpotResults($jackpot, [[1, 0], [2, 2]]);

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Open)
        ->and($winner->fresh()->status)->toBe(JackpotEntryStatus::Open);

    jackpotResults($jackpot, [2 => [0, 1]]);

    expect($jackpot->fresh())->status->toBe(JackpotStatus::Settled)->settled_at->not->toBeNull();
    expect($winner->fresh())->status->toBe(JackpotEntryStatus::Won)->correct_count->toBe(3)->prize->toBe(1_000_000)
        ->and($runnerUp->fresh())->status->toBe(JackpotEntryStatus::Won)->correct_count->toBe(2)->prize->toBe(100_000)
        ->and($loser->fresh())->status->toBe(JackpotEntryStatus::Lost)->correct_count->toBe(1)->prize->toBe(0);
    expect(app(Ledger::class)->balanceOf($allRight))->toBe(1_090_100)
        ->and(app(Ledger::class)->balanceOf($twoRight))->toBe(190_100)
        ->and(app(Ledger::class)->balanceOf($oneRight))->toBe(90_100);
    expect($allRight->walletTransactions()->latest('id')->first())
        ->type->toBe(WalletTransactionType::JackpotPrize)
        ->id->toBe($winner->fresh()->prize_transaction_id);
    expect(Activity::query()->where('event', 'jackpot-settled')->sole()->properties['winners'])->toBe(2);
});

test('a tier pool is split equally between its winners, rounded down to the cent', function () {
    $jackpot = openJackpot();
    $jackpot->update(['prize_tiers' => [['correct' => 3, 'pool' => 1_000_000], ['correct' => 2, 'pool' => 100_000]]]);
    $entries = collect(range(1, 3))->map(fn () => enterJackpot(bettor(), $jackpot, ['1', 'X', '2']));

    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);

    expect($entries->map(fn ($entry) => $entry->fresh()->prize)->all())->toBe([333_333, 333_333, 333_333]);
});

test('nobody wins a tier with no entries at that score', function () {
    $jackpot = openJackpot();
    $entry = enterJackpot(bettor(), $jackpot, ['2', '2', '2']);

    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);

    expect($entry->fresh())->status->toBe(JackpotEntryStatus::Lost)->correct_count->toBe(1)
        ->and(SettleJackpot::tiers($jackpot->fresh()))->toBe([
            ['correct' => 3, 'pool' => 1_000_000, 'winners' => 0, 'each' => 0],
            ['correct' => 2, 'pool' => 100_000, 'winners' => 0, 'each' => 0],
        ]);
});

test('winnings tax is taken from the prize above the entry price', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $player = bettor(100_000);
    $jackpot = openJackpot();
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);

    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);

    expect($entry->fresh()->prize)->toBe(1_000_000 - 198_020)
        ->and($player->walletTransactions()->latest('id')->first())
        ->type->toBe(WalletTransactionType::WinningsTax)
        ->amount->toBe(-198_020);
});

test('a void match counts as right for every entry', function () {
    $jackpot = openJackpot();
    $entry = enterJackpot(bettor(), $jackpot, ['1', '2', '2']);

    jackpotResults($jackpot, [[1, 0], null, [0, 3]]);

    expect($entry->fresh())->status->toBe(JackpotEntryStatus::Won)->correct_count->toBe(3)->prize->toBe(1_000_000);
});

test('more void matches than the limit cancels the jackpot and refunds every entry', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);

    jackpotResults($jackpot, [null, null, [0, 3]]);

    expect($jackpot->fresh())->status->toBe(JackpotStatus::Cancelled)->cancel_reason->toContain('2 of 3 matches were void')
        ->and($entry->fresh())->status->toBe(JackpotEntryStatus::Refunded)->prize->toBe(9_900)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000)
        ->and($player->walletTransactions()->latest('id')->first()->type)->toBe(WalletTransactionType::JackpotRefund);
});

test('a corrected result takes the prizes back and settles again', function () {
    $winner = bettor(100_000);
    $other = bettor(100_000);
    $jackpot = openJackpot();
    $first = enterJackpot($winner, $jackpot, ['1', 'X', '2']);
    $second = enterJackpot($other, $jackpot, ['1', 'X', '1']);

    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);
    jackpotResults($jackpot, [2 => [2, 1]]);

    expect($jackpot->fresh())->status->toBe(JackpotStatus::Settled)->settlement_version->toBe(2)
        ->and($first->fresh())->correct_count->toBe(2)->prize->toBe(100_000)
        ->and($second->fresh())->correct_count->toBe(3)->prize->toBe(1_000_000)
        ->and(app(Ledger::class)->balanceOf($winner))->toBe(190_100)
        ->and(app(Ledger::class)->balanceOf($other))->toBe(1_090_100);
});

test('recording the same result again changes nothing', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();
    enterJackpot($player, $jackpot, ['1', 'X', '2']);

    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);
    jackpotResults($jackpot, [2 => [0, 3]]);

    expect($jackpot->fresh()->settlement_version)->toBe(1)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(1_090_100);
});

test('a match moved more than 48 hours is void, and stays void when a result comes in', function () {
    $jackpot = openJackpot();
    $entry = enterJackpot(bettor(), $jackpot, ['1', '1', '2']);
    $moved = $jackpot->jackpotFixtures[1];
    $moved->fixture->update(['kicks_off_at' => $moved->kicks_off_at->addHours(49)]);
    $jackpot->jackpotFixtures[0]->fixture->update(['kicks_off_at' => $jackpot->jackpotFixtures[0]->kicks_off_at->addHours(47)]);

    expect(app(VoidPostponedJackpotMatches::class)->handle())->toBe(1)
        ->and($moved->fresh())->is_void->toBeTrue()->void_reason->not->toBeNull();

    jackpotResults($jackpot, [[1, 0], [0, 2], [0, 3]]);

    expect($moved->fresh()->is_void)->toBeTrue()
        ->and($entry->fresh())->status->toBe(JackpotEntryStatus::Won)->correct_count->toBe(3);
});

test('an admin can cancel an open jackpot, refunding every entry', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();
    $entry = enterJackpot($player, $jackpot, ['1', 'X', '2']);
    $admin = Admin::factory()->create();

    app(CancelJackpot::class)->handle($jackpot, 'Wrong matches picked', $admin);

    expect($jackpot->fresh())->status->toBe(JackpotStatus::Cancelled)->cancel_reason->toBe('Wrong matches picked')
        ->and($entry->fresh()->status)->toBe(JackpotEntryStatus::Refunded)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000)
        ->and(Activity::query()->where('event', 'jackpot-cancelled')->sole()->causer->is($admin))->toBeTrue();
});

test('a settled jackpot cannot be cancelled', function () {
    $jackpot = openJackpot();
    enterJackpot(bettor(), $jackpot, ['1', 'X', '2']);
    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);

    expect(fn () => app(CancelJackpot::class)->handle($jackpot, 'Too late'))->toThrow(ValidationException::class);
});

test('a result for a cancelled jackpot changes nothing', function () {
    $player = bettor(100_000);
    $jackpot = openJackpot();
    enterJackpot($player, $jackpot, ['1', 'X', '2']);
    app(CancelJackpot::class)->handle($jackpot, 'Called off');

    jackpotResults($jackpot, [[1, 0], [0, 0], [0, 3]]);

    expect($jackpot->fresh()->status)->toBe(JackpotStatus::Cancelled)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000);
});
