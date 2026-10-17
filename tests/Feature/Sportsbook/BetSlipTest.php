<?php

use App\Models\BookingCode;
use App\Models\Selection;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    RateLimiter::clear('booking-code-create:127.0.0.1');
    RateLimiter::clear('booking-code-load:127.0.0.1');
});

function slipPick(Selection $selection, ?string $price = null): array
{
    return ['fixture_id' => $selection->market->fixture_id, 'selection_id' => $selection->id, 'price' => $price ?? $selection->price()];
}

test('shows the picks with their combined odds and possible win before tax', function () {
    $selection = selectionFor(pricedFixture(['home_team' => 'Arsenal'], ['home' => '2.50', 'draw' => '3.40', 'away' => '3.50']));

    Livewire::test('sportsbook.slip')
        ->call('syncPicks', [slipPick($selection)])
        ->set('stake', '100')
        ->assertSee('Arsenal')
        ->assertSee('Possible win (before tax)')
        ->assertSee('KES 250.00');
});

test('asks guests to log in and tells signed-in players betting opens soon', function () {
    $selection = selectionFor(pricedFixture());

    Livewire::test('sportsbook.slip')->call('syncPicks', [slipPick($selection)])->assertSee('Log in to bet');

    $this->actingAs(User::factory()->create());
    Livewire::test('sportsbook.slip')->call('syncPicks', [slipPick($selection)])->assertSee('Betting opens soon')->assertDontSee('Log in to bet');
});

test('sends the new prices to the browser when the player accepts odds changes', function () {
    $selection = selectionFor(pricedFixture(prices: ['home' => '2.40', 'draw' => '3.40', 'away' => '3.50']));

    Livewire::test('sportsbook.slip')
        ->call('syncPicks', [slipPick($selection, '2.10')])
        ->assertSee('Some odds have changed since you added them.')
        ->call('acceptPriceChanges')
        ->assertDispatched('betslip-accept-prices', prices: [$selection->id => '2.40']);
});

test('creates a booking code for the slip', function () {
    $selection = selectionFor(pricedFixture());

    $component = Livewire::test('sportsbook.slip')->call('syncPicks', [slipPick($selection)])->call('createBookingCode');

    $bookingCode = BookingCode::sole();
    $component->assertSet('bookingCode', $bookingCode->code)->assertSee($bookingCode->code);
    expect($bookingCode->selection_ids)->toBe([$selection->id]);
});

test('limits booking code creation to ten a minute per visitor', function () {
    $selection = selectionFor(pricedFixture());
    $component = Livewire::test('sportsbook.slip')->call('syncPicks', [slipPick($selection)]);

    foreach (range(1, 10) as $attempt) {
        $component->call('createBookingCode');
    }
    $component->call('createBookingCode')->assertHasErrors(['booking']);

    $this->assertDatabaseCount('booking_codes', 10);
});

test('loads a booking code into the browser slip and reports closed selections', function () {
    $selection = selectionFor(pricedFixture());
    BookingCode::factory()->create(['code' => 'K7Q2ZP', 'selection_ids' => [$selection->id]]);

    Livewire::test('sportsbook.slip')
        ->set('codeToLoad', 'k7q2zp')
        ->call('loadBookingCode')
        ->assertHasNoErrors()
        ->assertDispatched('betslip-replace', picks: [slipPick($selection)]);
});

test('shows an error for a code that does not exist', function () {
    Livewire::test('sportsbook.slip')
        ->set('codeToLoad', 'NOPE42')
        ->call('loadBookingCode')
        ->assertHasErrors(['loadCode'])
        ->assertSee('That booking code does not exist.')
        ->assertNotDispatched('betslip-replace');
});

test('a shared booking code link opens the code in the bet slip', function () {
    Livewire::withQueryParams(['betslip' => 'K7Q2ZP'])
        ->test('pages::sportsbook.home')
        ->assertDispatched('load-booking-code', code: 'K7Q2ZP');
});
