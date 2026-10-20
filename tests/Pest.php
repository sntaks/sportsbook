<?php

use App\Accounts\Sms\SmsSender;
use App\Betting\PlaceBet;
use App\Enums\WalletTransactionType;
use App\Models\Bet;
use App\Models\Competition;
use App\Models\Fixture;
use App\Models\Market;
use App\Models\Selection;
use App\Models\User;
use App\Wallet\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery\LegacyMockInterface;
use Psr\Log\LoggerInterface;
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
 * Swap the SMS sender for one that records every message instead of sending it. Pass false to make every send fail.
 */
function fakeSms(bool $accepts = true): FakeSmsSender
{
    $sender = new FakeSmsSender($accepts);
    app()->instance(SmsSender::class, $sender);

    return $sender;
}

class FakeSmsSender implements SmsSender
{
    /** @var list<array{phone: string, message: string}> */
    public array $sent = [];

    public function __construct(private bool $accepts = true) {}

    public function send(string $phone, string $message): bool
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];

        return $this->accepts;
    }

    /**
     * The code in the latest message sent to a number.
     */
    public function lastCodeFor(string $phone): string
    {
        $message = collect($this->sent)->where('phone', $phone)->last()['message'] ?? '';

        preg_match('/\b(\d{6})\b/', $message, $matches);

        return $matches[1] ?? throw new RuntimeException("No code was sent to {$phone}.");
    }
}

/**
 * Point M-Pesa at the sandbox with test credentials and fake its token endpoint. Any other Safaricom request needs
 * its own Http::fake() in the test; anything unfaked fails the test, so no test can reach Safaricom.
 */
function fakeMpesa(): void
{
    config([
        'mpesa.environment' => 'sandbox',
        'mpesa.callback_secret' => MPESA_TEST_SECRET,
        'mpesa.callback_base_url' => 'https://callbacks.sportsbook.test',
        'mpesa.allowed_ips' => [],
        'mpesa.apps.c2b' => ['consumer_key' => 'c2b-key', 'consumer_secret' => 'c2b-secret'],
        'mpesa.apps.b2c' => ['consumer_key' => 'b2c-key', 'consumer_secret' => 'b2c-secret'],
        'mpesa.lnmo.short_code' => '174379',
        'mpesa.lnmo.passkey' => 'test-passkey',
        'mpesa.c2b.short_code' => '600000',
        'mpesa.b2c.short_code' => '600001',
        'mpesa.b2c.initiator_name' => 'testapi',
        'mpesa.b2c.security_credential' => 'initiator-password',
    ]);

    Http::preventStrayRequests();
    Http::fake(['sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'test-token', 'expires_in' => '3599'])]);
}

/**
 * Safaricom accepts every push, numbering them ws_CO_1, ws_CO_2, …
 */
function fakeStkPushAccepted(): void
{
    $pushes = 0;

    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => function () use (&$pushes) {
        $pushes++;

        return Http::response([
            'MerchantRequestID' => "MR-{$pushes}",
            'CheckoutRequestID' => "ws_CO_{$pushes}",
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
        ]);
    }]);
}

const MPESA_TEST_SECRET = 'test-callback-secret-0123456789abcdef0123456789';

/**
 * Replace the "mpesa" log channel with a spy; assert on it with shouldHaveReceived().
 */
function spyOnMpesaLog(): LegacyMockInterface
{
    $channel = Mockery::spy(LoggerInterface::class);
    Log::shouldReceive('channel')->with('mpesa')->andReturn($channel);

    return $channel;
}

/**
 * The URL Safaricom would post a callback to, e.g. mpesaCallbackUrl('stk').
 */
function mpesaCallbackUrl(string $path): string
{
    return '/api/mpesa/'.MPESA_TEST_SECRET.'/'.$path;
}

/**
 * The body Safaricom posts to the STK callback URL.
 *
 * @return array<string, mixed>
 */
function stkCallback(string $checkoutRequestId, int $resultCode = 0, ?int $amount = 500, ?string $phone = '254712345678', string $receipt = 'TJK1A2B3C4'): array
{
    $callback = [
        'MerchantRequestID' => 'MR-1',
        'CheckoutRequestID' => $checkoutRequestId,
        'ResultCode' => $resultCode,
        'ResultDesc' => $resultCode === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user',
    ];

    if ($resultCode === 0) {
        $callback['CallbackMetadata'] = ['Item' => [
            ['Name' => 'Amount', 'Value' => $amount],
            ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
            ['Name' => 'TransactionDate', 'Value' => 20261018101500],
            ['Name' => 'PhoneNumber', 'Value' => (int) $phone],
        ]];
    }

    return ['Body' => ['stkCallback' => $callback]];
}

/**
 * A player with money in their wallet, credited through the ledger like a real deposit.
 */
function fundedPlayer(int $cents = 100_000, array $attributes = []): User
{
    $player = User::factory()->create(['phone' => '254712345678', ...$attributes]);
    app(Ledger::class)->post($player, WalletTransactionType::Deposit, $cents, "test-funding:{$player->id}");

    return $player;
}

/**
 * The body Safaricom posts to a result URL (B2C payout, transaction status or balance).
 *
 * @param  array<string, mixed>  $parameters  ResultParameters as Key => Value.
 * @return array<string, mixed>
 */
function mpesaResult(string $conversationId, int $resultCode = 0, array $parameters = [], ?string $transactionId = 'TKP1Q2R3S4', string $originatorConversationId = 'orig-1'): array
{
    return ['Result' => [
        'ResultType' => 0,
        'ResultCode' => $resultCode,
        'ResultDesc' => $resultCode === 0 ? 'The service request is processed successfully.' : 'The initiator information is invalid.',
        'OriginatorConversationID' => $originatorConversationId,
        'ConversationID' => $conversationId,
        'TransactionID' => $transactionId,
        'ResultParameters' => ['ResultParameter' => collect($parameters)->map(fn ($value, $key) => ['Key' => $key, 'Value' => $value])->values()->all()],
        'ReferenceData' => ['ReferenceItem' => ['Key' => 'QueueTimeoutURL', 'Value' => 'https://callbacks.sportsbook.test/timeout']],
    ]];
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

/**
 * An upcoming fixture in a synced competition with an open 1X2 market priced at the given prices.
 *
 * @param  array<string, mixed>  $fixtureAttributes
 * @param  array<string, string>  $prices  Outcome => price.
 */
function pricedFixture(array $fixtureAttributes = [], array $prices = ['home' => '2.10', 'draw' => '3.40', 'away' => '3.50']): Fixture
{
    $fixture = Fixture::factory()
        ->for(Competition::factory()->synced())
        ->create($fixtureAttributes);
    $market = Market::factory()->for($fixture)->create();
    $names = ['home' => $fixture->home_team, 'draw' => 'Draw', 'away' => $fixture->away_team];

    foreach ($prices as $outcome => $price) {
        Selection::factory()->for($market)->create(['outcome' => $outcome, 'name' => $names[$outcome] ?? $outcome, 'feed_price' => $price]);
    }

    return $fixture->load('matchResultMarket.selections');
}

/**
 * A bet slip pick as the browser sends it, at the price the player saw (the current price by default).
 *
 * @return array{fixture_id: int, selection_id: int, price: string|null}
 */
function slipPick(Selection $selection, ?string $price = null): array
{
    return ['fixture_id' => $selection->market->fixture_id, 'selection_id' => $selection->id, 'price' => $price ?? $selection->price()];
}

/**
 * The fixture's 1X2 selection for an outcome.
 */
function selectionFor(Fixture $fixture, string $outcome = 'home'): Selection
{
    return $fixture->matchResultMarket->selections->firstWhere('outcome', $outcome);
}

/**
 * A player with a balance, any number per test (fundedPlayer() uses one fixed number).
 */
function bettor(int $cents = 100_000): User
{
    $player = User::factory()->create();
    app(Ledger::class)->post($player, WalletTransactionType::Deposit, $cents, "test-funding:{$player->id}");

    return $player;
}

/**
 * Place a bet on the given fixtures' 1X2 outcomes, e.g. [[$fixture, 'home'], [$other, 'draw']].
 *
 * @param  list<array{0: Fixture, 1: string}>  $picks
 */
function betOn(User $player, array $picks, string $stake = '100'): Bet
{
    return app(PlaceBet::class)->handle($player, array_map(fn (array $pick): array => slipPick(selectionFor($pick[0], $pick[1])), $picks), $stake);
}
