<?php

use App\Enums\JackpotStatus;
use App\Enums\UserStatus;
use App\Models\Jackpot;
use App\Models\JackpotEntry;
use App\Wallet\Ledger;

/**
 * The API body for picks, in match order.
 *
 * @param  list<string>  $picks
 * @return list<array{match_id: int, pick: string}>
 */
function apiJackpotPicks(Jackpot $jackpot, array $picks): array
{
    return $jackpot->jackpotFixtures->values()
        ->map(fn ($match, int $index): array => ['match_id' => $match->id, 'pick' => $picks[$index]])
        ->all();
}

test('the current jackpot returns 200 with its matches and prize tiers to guests', function () {
    $jackpot = openJackpot();
    $first = $jackpot->jackpotFixtures->first();

    $this->getJson(route('api.v1.jackpots.current'))
        ->assertOk()
        ->assertJsonPath('data.id', $jackpot->id)
        ->assertJsonPath('data.entry_price_cents', 9_900)
        ->assertJsonPath('data.is_taking_entries', true)
        ->assertJsonPath('data.closes_at', $first->fixture->kicks_off_at->toIso8601String())
        ->assertJsonPath('data.prize_tiers', [['correct' => 3, 'pool_cents' => 1_000_000], ['correct' => 2, 'pool_cents' => 100_000]])
        ->assertJsonCount(3, 'data.matches')
        ->assertJsonPath('data.matches.0.id', $first->id)
        ->assertJsonPath('data.matches.0.fixture_name', $first->fixture_name);
});

test('the current jackpot returns 404 when none is open', function () {
    Jackpot::factory()->withMatches(3)->create();

    $this->getJson(route('api.v1.jackpots.current'))->assertNotFound();
});

test('entering a jackpot returns 201 with the entry and takes the price from the wallet', function () {
    $jackpot = openJackpot();
    $player = fundedPlayer(100_000);

    $response = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.jackpots.entries.store', $jackpot), [
        'picks' => apiJackpotPicks($jackpot, ['1', 'X', '2']),
        'entry_key' => 'entry-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.stake_cents', 9_900)
        ->assertJsonPath('data.jackpot.id', $jackpot->id)
        ->assertJsonPath('data.picks.1.pick', 'X')
        ->assertJsonPath('data.picks.1.is_right', null);
    expect(JackpotEntry::sole()->reference)->toBe($response->json('data.reference'))
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000 - 9_900);
});

test('sending the same entry again returns 200 with the entry already made and charges once', function () {
    $jackpot = openJackpot();
    $player = fundedPlayer(100_000);
    $body = ['picks' => apiJackpotPicks($jackpot, ['1', 'X', '2']), 'entry_key' => 'entry-1'];

    $first = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.jackpots.entries.store', $jackpot), $body)->assertCreated();
    $this->postJson(route('api.v1.jackpots.entries.store', $jackpot), $body)
        ->assertOk()
        ->assertJsonPath('data.reference', $first->json('data.reference'));

    expect(JackpotEntry::count())->toBe(1)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(100_000 - 9_900);
});

test('a refused entry returns 422 with a reason the app can act on', function (Closure $makePlayer, Closure $makePicks, string $reason, string $message) {
    $jackpot = openJackpot();

    $this->actingAs($makePlayer(), 'sanctum')
        ->postJson(route('api.v1.jackpots.entries.store', $jackpot), ['picks' => $makePicks($jackpot), 'entry_key' => 'entry-1'])
        ->assertUnprocessable()
        ->assertExactJson(['message' => $message, 'reason' => $reason]);

    expect(JackpotEntry::count())->toBe(0);
})->with([
    'suspended' => [fn () => fundedPlayer(100_000, ['status' => UserStatus::Suspended]), fn (Jackpot $jackpot) => apiJackpotPicks($jackpot, ['1', 'X', '2']), 'not-allowed', 'Your account is suspended. Please contact support.'],
    'a match left out' => [fn () => fundedPlayer(), fn (Jackpot $jackpot) => array_slice(apiJackpotPicks($jackpot, ['1', 'X', '2']), 0, 2), 'picks', 'Pick 1, X or 2 for every match.'],
    'not 1, X or 2' => [fn () => fundedPlayer(), fn (Jackpot $jackpot) => apiJackpotPicks($jackpot, ['1', 'X', 'home']), 'picks', 'Pick 1, X or 2 for every match.'],
    'too little money' => [fn () => fundedPlayer(5_000), fn (Jackpot $jackpot) => apiJackpotPicks($jackpot, ['1', 'X', '2']), 'insufficient-funds', 'Your balance is KES 50.00. Deposit to enter this jackpot.'],
]);

test('entering after the first kick-off returns 422 closed', function () {
    $jackpot = openJackpot();
    $jackpot->jackpotFixtures->first()->fixture->update(['kicks_off_at' => now()->subMinute()]);

    $this->actingAs(fundedPlayer(), 'sanctum')
        ->postJson(route('api.v1.jackpots.entries.store', $jackpot), ['picks' => apiJackpotPicks($jackpot, ['1', 'X', '2']), 'entry_key' => 'entry-1'])
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'closed');
});

test('entering without picks or an entry key returns 422', function () {
    $jackpot = openJackpot();

    $this->actingAs(fundedPlayer(), 'sanctum')
        ->postJson(route('api.v1.jackpots.entries.store', $jackpot), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['picks' => 'The picks field is required.', 'entry_key' => 'The entry key field is required.']);
});

test('entering a draft jackpot returns 404', function () {
    $draft = Jackpot::factory()->withMatches(3)->create();

    $this->actingAs(fundedPlayer(), 'sanctum')
        ->postJson(route('api.v1.jackpots.entries.store', $draft), ['picks' => apiJackpotPicks($draft->load('jackpotFixtures'), ['1', 'X', '2']), 'entry_key' => 'entry-1'])
        ->assertNotFound();

    expect($draft->fresh()->status)->toBe(JackpotStatus::Draft);
});

test('my jackpot entries return 200 with only my own entries', function () {
    $jackpot = openJackpot();
    $player = bettor();
    $mine = enterJackpot($player, $jackpot, ['1', 'X', '2']);
    enterJackpot(bettor(), $jackpot, ['2', '2', '2']);
    $jackpot->jackpotFixtures->first()->update(['outcome' => '1']);

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.jackpot-entries.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference', $mine->reference)
        ->assertJsonPath('data.0.picks.0.outcome', '1')
        ->assertJsonPath('data.0.picks.0.is_right', true);
});

test('jackpot entries return 401 without a token', function () {
    $jackpot = openJackpot();

    $this->getJson(route('api.v1.jackpot-entries.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.jackpots.entries.store', $jackpot), [])->assertUnauthorized();
});
