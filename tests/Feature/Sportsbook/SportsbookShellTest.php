<?php

use App\Models\User;

test('the home page renders the navigation, an empty bet slip and the age notice', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSeeInOrder(['Sports', 'Live', 'Jackpot', 'Virtuals', 'Crash', 'Promotions'])
        ->assertSee('Your bet slip is empty')
        ->assertSee('Betting is restricted to persons aged 18 years and above.');
});

test('guests are offered log in and register links', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('each public page renders inside the shell', function (string $routeName, string $heading) {
    $response = $this->get(route($routeName));

    $response->assertOk()
        ->assertSee($heading)
        ->assertSee('Your bet slip is empty');
})->with([
    'live' => ['live', 'No live matches right now'],
    'jackpot' => ['jackpot', 'No jackpot is open right now'],
    'promotions' => ['promotions', 'No promotions running right now'],
    'terms' => ['terms', 'Terms & Conditions'],
    'privacy' => ['privacy', 'Privacy Policy'],
    'responsible gambling' => ['responsible-gambling', 'Responsible Gambling'],
]);

test('virtuals and crash games are marked as coming soon', function (string $routeName) {
    $response = $this->get(route($routeName));

    $response->assertOk()->assertSee('Coming soon');
})->with(['virtuals', 'crash']);

test('guests are redirected to log in before seeing their bets', function () {
    $response = $this->get(route('my-bets'));

    $response->assertRedirect(route('login'));
});

test('a signed-in player sees their bets page', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('my-bets'));

    $response->assertOk()->assertSee('No open bets');
});

test('the footer shows the licence line only when one is configured', function () {
    config(['sportsbook.licence_text' => 'Licensed by the BCLB under licence no. 0000']);

    $this->get(route('home'))->assertSee('Licensed by the BCLB under licence no. 0000');

    config(['sportsbook.licence_text' => null]);

    $this->get(route('home'))->assertDontSee('Licensed by the BCLB');
});

test('escapes the player\'s name in the navigation', function () {
    $this->actingAs(User::factory()->create(['name' => "Wanjiru <script>alert('x')</script>"]));

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('Wanjiru &lt;script&gt;', escape: false)
        ->assertDontSee("<script>alert('x')</script>", escape: false);
});
