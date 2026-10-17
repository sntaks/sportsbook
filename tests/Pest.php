<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Decode a recorded Odds API response from tests/Fixtures/odds-api.
 *
 * @return list<array<string, mixed>>
 */
function oddsApiFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/odds-api/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Use the live Odds API driver against a fake host. Any request without a matching Http::fake() fails the test,
 * so no test can ever reach the real API.
 */
function useLiveOddsApi(): void
{
    config([
        'sportsbook.odds_api.driver' => 'live',
        'sportsbook.odds_api.region' => 'uk',
        'services.odds_api.key' => 'test-api-key',
        'services.odds_api.base_url' => 'https://odds-api.test/v4',
    ]);

    Http::preventStrayRequests();
}
