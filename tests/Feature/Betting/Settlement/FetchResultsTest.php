<?php

use App\Betting\Settlement\FetchResults;
use App\Enums\BetStatus;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\OddsApiCall;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    Http::fake(['odds-api.test/v4/sports/*/scores*' => Http::response(oddsApiFixture('scores-soccer_epl'), 200, ['x-requests-last' => '2', 'x-requests-remaining' => '484'])]);
    $this->travelTo('2026-10-17 10:00:00');
    $this->competition = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_epl']);
});

/**
 * A fixture from the recorded scores feed (Everton 0-2 Chelsea by default), with a bet on it placed before kick-off.
 *
 * @param  array<string, mixed>  $attributes
 */
function feedFixtureWithBet(Competition $competition, array $attributes = [], string $outcome = 'away'): Fixture
{
    $fixture = pricedFixture([
        'competition_id' => $competition->id,
        'external_id' => '764e6513cb3f348881503df70ccb90e0',
        'home_team' => 'Everton',
        'away_team' => 'Chelsea',
        'kicks_off_at' => '2026-10-17 11:30:00',
        ...$attributes,
    ]);
    betOn(bettor(), [[$fixture, $outcome]]);

    return $fixture;
}

test('fetches scores for a competition with matches waiting on a result and settles them', function () {
    $fixture = feedFixtureWithBet($this->competition);
    $notFinished = feedFixtureWithBet($this->competition, ['external_id' => 'dfd5d6648be46426e418801584d46a68', 'home_team' => 'Bournemouth', 'away_team' => 'Sunderland']);
    $this->travelTo('2026-10-18 09:00:00');

    expect(app(FetchResults::class)->handle())->toBe(['competitions' => 1, 'results' => 1]);

    expect($fixture->result)->home_score->toBe(0)->away_score->toBe(2)->source->toBe(FixtureResult::SOURCE_FEED)
        ->and($fixture->betLegs->sole()->bet->status)->toBe(BetStatus::Won)
        ->and($notFinished->result()->exists())->toBeFalse()
        ->and($this->competition->fresh()->scores_checked_at->equalTo(now()))->toBeTrue()
        ->and(OddsApiCall::sole()->credits_spent)->toBe(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/sports/soccer_epl/scores') && str_contains($request->url(), 'daysFrom=3'));
});

test('spends no credits until a match with open bets has had time to finish', function () {
    feedFixtureWithBet($this->competition, ['kicks_off_at' => '2026-10-17 11:30:00']);
    $this->travelTo('2026-10-17 13:30:00');

    expect(app(FetchResults::class)->handle())->toBe(['competitions' => 0, 'results' => 0]);
    Http::assertNothingSent();
});

test('spends no credits on matches without open bets, with a result already, or beyond the feed\'s three days', function () {
    pricedFixture(['competition_id' => $this->competition->id, 'kicks_off_at' => '2026-10-17 11:30:00']);
    FixtureResult::factory()->for(feedFixtureWithBet($this->competition, ['external_id' => 'already-settled']))->create();
    feedFixtureWithBet($this->competition, ['external_id' => 'too-old', 'kicks_off_at' => '2026-10-17 11:30:00']);
    $this->travelTo('2026-10-20 12:00:00');

    expect(app(FetchResults::class)->handle()['competitions'])->toBe(0);
    Http::assertNothingSent();
});

test('asks the feed about a competition at most once every 12 hours', function () {
    feedFixtureWithBet($this->competition, ['external_id' => 'not-in-the-feed']);
    $this->travelTo('2026-10-18 09:00:00');

    app(FetchResults::class)->handle();
    $this->travelTo('2026-10-18 20:59:00');
    app(FetchResults::class)->handle();
    $this->travelTo('2026-10-18 21:00:00');
    app(FetchResults::class)->handle();

    Http::assertSentCount(2);
});

test('leaves competitions set to manual settlement to the admins', function () {
    $championsLeague = Competition::factory()->synced()->create(['odds_api_key' => 'soccer_uefa_champs_league']);
    feedFixtureWithBet($championsLeague);
    $this->travelTo('2026-10-18 09:00:00');

    expect($championsLeague->auto_settle)->toBeFalse()
        ->and(app(FetchResults::class)->handle()['competitions'])->toBe(0);
    Http::assertNothingSent();
});

test('stops when the monthly credit budget would be exceeded', function () {
    config(['sportsbook.odds_api.monthly_credit_cap' => 1]);
    feedFixtureWithBet($this->competition);
    $this->travelTo('2026-10-18 09:00:00');

    expect(app(FetchResults::class)->handle())->toBe(['competitions' => 0, 'results' => 0]);
    Http::assertNothingSent();
});

test('the settle command runs hourly and settles from the feed', function () {
    $fixture = feedFixtureWithBet($this->competition);
    $this->travelTo('2026-10-18 09:00:00');

    $this->artisan('sportsbook:settle')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'sportsbook:settle'));
    expect($fixture->result()->exists())->toBeTrue()
        ->and($event?->expression)->toBe('0 * * * *');
});
