<?php

use App\Enums\MarketStatus;
use App\Enums\SuspensionReason;
use App\Models\BookingCode;
use App\Models\User;

test('quoting a slip returns 200 with the combined odds and possible win in cents', function () {
    $arsenal = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea']);
    $madrid = pricedFixture(['home_team' => 'Real Madrid']);

    $response = $this->postJson(route('api.v1.slip.quote'), [
        'picks' => [slipPick(selectionFor($arsenal, 'home')), slipPick(selectionFor($madrid, 'draw'))],
        'stake' => '100',
    ]);

    $response->assertOk()
        ->assertJsonPath('lines.0.fixture_name', 'Arsenal v Chelsea')
        ->assertJsonPath('lines.0.status', 'ok')
        ->assertJsonPath('lines.0.price', '2.10')
        ->assertJsonPath('total_odds', '7.14')
        ->assertJsonPath('possible_win_cents', 71_400)
        ->assertJsonPath('stake_errors', [])
        ->assertJsonPath('bettable_count', 2);
});

test('quoting a slip flags a moved price and returns the current one', function () {
    $selection = selectionFor(pricedFixture());

    $response = $this->postJson(route('api.v1.slip.quote'), ['picks' => [slipPick($selection, '1.90')]]);

    $response->assertOk()
        ->assertJsonPath('lines.0.status', 'price_changed')
        ->assertJsonPath('lines.0.seen_price', '1.90')
        ->assertJsonPath("changed_prices.{$selection->id}", '2.10');
});

test('quoting a slip marks a suspended market unavailable and leaves it out of the odds', function () {
    $fixture = pricedFixture();
    $selection = selectionFor($fixture);
    $selection->market->update(['status' => MarketStatus::Suspended, 'suspension_reason' => SuspensionReason::Manual]);

    $response = $this->postJson(route('api.v1.slip.quote'), ['picks' => [slipPick($selection)]]);

    $response->assertOk()
        ->assertJsonPath('lines.0.status', 'unavailable')
        ->assertJsonPath('lines.0.price', null)
        ->assertJsonPath('lines.0.message', 'This market is suspended.')
        ->assertJsonPath('total_odds', '0.00')
        ->assertJsonPath('bettable_count', 0);
});

test('quoting a slip without a selection id returns 422', function () {
    $this->postJson(route('api.v1.slip.quote'), ['picks' => [['price' => '2.10']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['picks.0.selection_id']);
});

test('a booking code returns 201 and loads back with 200 as picks at today\'s prices', function () {
    $player = User::factory()->create();
    $selection = selectionFor(pricedFixture());

    $created = $this->actingAs($player, 'sanctum')
        ->postJson(route('api.v1.booking-codes.store'), ['selection_ids' => [$selection->id]]);

    $created->assertCreated()->assertJsonPath('selection_ids', [$selection->id]);
    expect(BookingCode::sole()->created_by_user_id)->toBe($player->id);

    $this->getJson(route('api.v1.booking-codes.show', strtolower($created->json('code'))))
        ->assertOk()
        ->assertJsonPath('code', $created->json('code'))
        ->assertJsonPath('picks', [slipPick($selection)])
        ->assertJsonPath('unavailable', []);
});

test('a booking code with nothing open for betting returns 422 and is not saved', function () {
    $selection = selectionFor(pricedFixture(['kicks_off_at' => now()->subMinutes(5)]));

    $this->postJson(route('api.v1.booking-codes.store'), ['selection_ids' => [$selection->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['selection_ids' => 'Add at least one selection that is open for betting.']);

    expect(BookingCode::count())->toBe(0);
});

test('loading an unknown or expired booking code returns 404', function (Closure $makeCode, string $message) {
    $makeCode();

    $this->getJson(route('api.v1.booking-codes.show', 'K7Q2ZP'))
        ->assertNotFound()
        ->assertJsonPath('message', $message);
})->with([
    'unknown' => [fn () => null, 'That booking code does not exist.'],
    'expired' => [fn () => BookingCode::factory()->expired()->create(['code' => 'K7Q2ZP']), 'That booking code has expired because a match on it has started.'],
]);

test('creating booking codes is limited to 10 a minute with 429', function () {
    $selection = selectionFor(pricedFixture());

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('api.v1.booking-codes.store'), ['selection_ids' => [$selection->id]])->assertCreated();
    }

    $this->postJson(route('api.v1.booking-codes.store'), ['selection_ids' => [$selection->id]])->assertTooManyRequests();
});

test('loading booking codes is limited to 30 a minute with 429', function () {
    foreach (range(1, 30) as $attempt) {
        $this->getJson(route('api.v1.booking-codes.show', 'K7Q2ZP'))->assertNotFound();
    }

    $this->getJson(route('api.v1.booking-codes.show', 'K7Q2ZP'))->assertTooManyRequests();
});
