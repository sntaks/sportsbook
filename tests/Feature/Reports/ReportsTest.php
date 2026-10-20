<?php

use App\Betting\Jackpot\CancelJackpot;
use App\Betting\PlaceBet;
use App\Betting\Settlement\CancelBet;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Jackpot;
use App\Models\User;
use App\Reports\Period;
use App\Reports\Reports;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 15)->setTime(12, 0));
});

/**
 * Record a full-time score for one of the jackpot's matches (0 is the first): from the feed, or as an admin's
 * correction when an admin is given.
 */
function jackpotMatchResult(Jackpot $jackpot, int $match, int $home, int $away, ?Admin $admin = null): void
{
    settleFixture($jackpot->jackpotFixtures()->with('fixture')->get()[$match]->fixture, $home, $away, $admin);
}

test('GGR is cash stakes less refunds and winnings before tax, NGR also takes off free-bet winnings, and tax stays apart', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $fixture = pricedFixture();
    betOn(bettor(), [[$fixture, 'home']]);
    betOn(bettor(), [[$fixture, 'away']]);
    app(CancelBet::class)->handle(betOn(bettor(), [[pricedFixture(), 'home']]), Admin::factory()->create(), 'Placed in error');
    $freeBetPlayer = bettor();
    app(PlaceBet::class)->handle($freeBetPlayer, [slipPick(selectionFor($fixture))], null, freeBetId: freeBetFor($freeBetPlayer)->id);

    settleFixture($fixture, 2, 0);

    expect(app(Reports::class)->summary(Period::lastDays(1)))->toMatchArray([
        'turnover' => 30_000,
        'stakes' => 30_000,
        // 300 staked - 100 returned - 210 won.
        'sportsbook_ggr' => -1_000,
        // The free bet won 110 over its stake.
        'promotion_costs' => 11_000,
        'ngr' => -12_000,
        'hold' => -3.3,
        // 20% of 110 profit on each winning bet.
        'taxes' => 4_400,
        'deposits' => 400_000,
        'withdrawals' => 0,
        'active_players' => 4,
        'sign_ups' => 4,
    ]);
});

test('a winning multi\'s bonus is a promotion cost, not part of GGR', function () {
    $fixtures = [pricedFixture(), pricedFixture(), pricedFixture()];
    $multi = betOn(bettor(), array_map(fn ($fixture): array => [$fixture, 'home'], $fixtures));

    foreach ($fixtures as $fixture) {
        settleFixture($fixture, 1, 0);
    }

    $multi->refresh();
    expect($multi->bonus)->toBeGreaterThan(0)
        ->and(app(Reports::class)->summary(Period::lastDays(1)))->toMatchArray([
            'sportsbook_ggr' => 10_000 - ($multi->payout - $multi->bonus),
            'promotion_costs' => $multi->bonus,
            'ngr' => 10_000 - $multi->payout,
        ]);
});

test('a corrected result counts on the day it was corrected, taking back winnings and their tax', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $fixture = pricedFixture(['kicks_off_at' => now()->addHour()]);
    betOn(bettor(), [[$fixture, 'home']]);
    settleFixture($fixture, 2, 0);

    $this->travel(2)->days();
    settleFixture($fixture, 0, 1, Admin::factory()->create());

    $reports = app(Reports::class);
    $period = Period::between('2026-10-15', '2026-10-17');

    expect(collect($reports->revenueByDay($period))->keyBy('date')->map->sportsbook_ggr->all())
        ->toBe(['2026-10-17' => 21_000, '2026-10-16' => 0, '2026-10-15' => -11_000])
        ->and(collect($reports->taxByDay($period))->keyBy('date')->map->winnings_tax->all())
        ->toBe(['2026-10-17' => -2_200, '2026-10-16' => 0, '2026-10-15' => 2_200])
        ->and($reports->summary($period))->toMatchArray(['sportsbook_ggr' => 10_000, 'taxes' => 0]);
});

test('jackpot GGR is entries less prizes before tax, a correction moves the prize on its own day, and refunds net out', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $jackpot = openJackpot();
    enterJackpot(bettor(), $jackpot, ['1', 'X', '2']);
    enterJackpot(bettor(), $jackpot, ['2', '2', '2']);
    $cancelled = openJackpot();
    enterJackpot(bettor(), $cancelled, ['1', '1', '1']);
    app(CancelJackpot::class)->handle($cancelled, 'Matches moved');
    jackpotMatchResult($jackpot, 0, 1, 0);
    jackpotMatchResult($jackpot, 1, 2, 2);
    jackpotMatchResult($jackpot, 2, 0, 1);

    $this->travel(1)->day();
    // The third match was really 1-0: the winner had two right, for the KES 1,000 pool.
    jackpotMatchResult($jackpot, 2, 1, 0, Admin::factory()->create());

    $reports = app(Reports::class);
    $days = collect($reports->revenueByDay(Period::between('2026-10-15', '2026-10-16')))->keyBy('date');
    $taxDays = collect($reports->taxByDay(Period::between('2026-10-15', '2026-10-16')))->keyBy('date');

    expect($days['2026-10-15'])->toMatchArray(['jackpot_entries' => 29_700, 'jackpot_ggr' => 19_800 - 1_000_000])
        ->and($days['2026-10-16'])->toMatchArray(['jackpot_entries' => 0, 'jackpot_ggr' => 1_000_000 - 100_000])
        // 20% of each prize less the entry price.
        ->and($taxDays['2026-10-15']['winnings_tax'])->toBe(198_020)
        ->and($taxDays['2026-10-16']['winnings_tax'])->toBe(18_020 - 198_020);
});

test('money lands on the EAT day it was posted', function () {
    $player = User::factory()->create();
    $ledger = app(Ledger::class);

    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 14)->setTime(23, 30));
    $ledger->post($player, WalletTransactionType::Deposit, 10_000, 'test:1');
    // 00:30 on the 15th in Nairobi is still the 14th in UTC.
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 15)->setTime(0, 30));
    $ledger->post($player, WalletTransactionType::Deposit, 20_000, 'test:2');
    $ledger->post($player, WalletTransactionType::Withdrawal, -5_000, 'test:3');

    expect(app(Reports::class)->paymentsByDay(Period::between('2026-10-14', '2026-10-15')))->toBe([
        ['date' => '2026-10-15', 'deposits' => 20_000, 'withdrawals' => 5_000, 'net' => 15_000],
        ['date' => '2026-10-14', 'deposits' => 10_000, 'withdrawals' => 0, 'net' => 10_000],
    ]);
});

test('turnover splits a multi\'s stake equally across its legs and leaves out free bets', function () {
    [$first, $second, $third] = [pricedFixture(), pricedFixture(), pricedFixture()];
    $player = bettor();
    betOn($player, [[$first, 'home'], [$second, 'home'], [$third, 'home']], '300');
    betOn($player, [[$first, 'away']], '100');
    app(PlaceBet::class)->handle($player, [slipPick(selectionFor($second))], null, freeBetId: freeBetFor($player)->id);

    $byCompetition = app(Reports::class)->turnoverBy('competition', Period::lastDays(1));

    expect($byCompetition[0])->toBe(['label' => $first->competition->name, 'bets' => 2, 'turnover' => 20_000, 'share' => 50.0])
        ->and(collect($byCompetition)->keyBy('label')->except($first->competition->name)->all())->toEqualCanonicalizing([
            $second->competition->name => ['label' => $second->competition->name, 'bets' => 1, 'turnover' => 10_000, 'share' => 25.0],
            $third->competition->name => ['label' => $third->competition->name, 'bets' => 1, 'turnover' => 10_000, 'share' => 25.0],
        ])->and(app(Reports::class)->turnoverBy('market', Period::lastDays(1)))->toBe([
            ['label' => '1X2', 'bets' => 2, 'turnover' => 40_000, 'share' => 100.0],
        ]);
});

test('top players rank net results on bets after tax, leaving out deposits', function () {
    config(['sportsbook.settlement.winnings_tax_rate' => 0.2]);
    $fixture = pricedFixture();
    $winner = bettor();
    $loser = bettor();
    $bigLoser = bettor();
    betOn($winner, [[$fixture, 'home']]);
    betOn($loser, [[$fixture, 'away']]);
    betOn($bigLoser, [[$fixture, 'draw']], '500');
    settleFixture($fixture, 1, 0);

    $reports = app(Reports::class);

    expect($reports->topPlayers(Period::lastDays(1), winners: true))->toBe([
        ['user_id' => $winner->id, 'name' => $winner->name, 'phone' => $winner->phone, 'net' => 8_800, 'staked' => 10_000],
    ])->and(collect($reports->topPlayers(Period::lastDays(1), winners: false))->pluck('net', 'user_id')->all())
        ->toBe([$bigLoser->id => -50_000, $loser->id => -10_000]);
});

test('a range is put the right way round, never runs past today and keeps at most its last 366 days', function () {
    $reversed = Period::between('2026-10-12', '2026-10-01');
    $future = Period::between('2026-10-10', '2026-12-01');
    $long = Period::between('2020-01-01', '2026-10-15');
    $default = Period::between(null, 'not a date');

    expect([$reversed->firstDay->toDateString(), $reversed->lastDay->toDateString()])->toBe(['2026-10-01', '2026-10-12'])
        ->and($future->lastDay->toDateString())->toBe('2026-10-15')
        ->and($long->dayCount())->toBe(366)
        ->and($long->lastDay->toDateString())->toBe('2026-10-15')
        ->and([$default->firstDay->toDateString(), $default->lastDay->toDateString()])->toBe(['2026-09-16', '2026-10-15'])
        ->and($reversed->start()->toDateTimeString())->toBe('2026-09-30 21:00:00');
});

test('the period before a run to today stops at the same time of day; an earlier run is compared in whole days', function () {
    $today = Period::lastDays(1)->previous();
    $week = Period::between('2026-10-01', '2026-10-07')->previous();

    expect([$today->firstDay->toDateString(), $today->lastDay->toDateString()])->toBe(['2026-10-14', '2026-10-14'])
        ->and($today->end()->toDateTimeString())->toBe('2026-10-14 09:00:00')
        ->and([$week->firstDay->toDateString(), $week->lastDay->toDateString()])->toBe(['2026-09-24', '2026-09-30'])
        ->and($week->end()->toDateTimeString())->toBe('2026-09-30 21:00:00');
});

test('daily figures count each day\'s players once, with its sign-ups and money', function () {
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 14)->setTime(10, 0));
    $regular = bettor();
    betOn($regular, [[pricedFixture(), 'home']], '30');
    betOn($regular, [[pricedFixture(), 'home']], '20');
    betOn(bettor(), [[pricedFixture(), 'home']], '50');
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 15)->setTime(9, 0));
    betOn($regular, [[pricedFixture(), 'home']], '40');
    User::factory()->create();

    $days = collect(app(Reports::class)->dailyFigures(Period::between('2026-10-14', '2026-10-15')))
        ->map(fn (array $day): array => [$day['date'], $day['turnover'], $day['active_players'], $day['sign_ups']]);

    expect($days->all())->toBe([
        ['2026-10-15', 4_000, 1, 1],
        ['2026-10-14', 10_000, 2, 2],
    ]);
});

test('reports read through the reports connection when one is set', function () {
    config([
        'database.connections.reports' => [...config('database.connections.sqlite'), 'database' => ':memory:', 'foreign_key_constraints' => false],
        'sportsbook.reports.connection' => 'reports',
    ]);
    Artisan::call('migrate', ['--database' => 'reports']);
    app(Ledger::class)->post(User::factory()->create(), WalletTransactionType::Deposit, 10_000, 'test:main');
    DB::connection('reports')->table('wallet_transactions')->insert([
        'wallet_id' => 1, 'type' => WalletTransactionType::Deposit->value, 'amount' => 25_000, 'balance_after' => 25_000,
        'idempotency_key' => 'test:replica', 'created_at' => now(),
    ]);

    expect(app(Reports::class)->summary(Period::lastDays(1))['deposits'])->toBe(25_000);
});
