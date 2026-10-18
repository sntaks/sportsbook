<?php

use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Withdrawal;
use App\Wallet\Ledger;
use App\Wallet\Withdrawals\PayOutWithdrawal;
use App\Wallet\Withdrawals\RequestWithdrawal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * A queued KES 500 withdrawal for a player who had KES 1,000.
 */
function queuedWithdrawal(): Withdrawal
{
    Queue::fake([SendWithdrawalPayout::class]);

    return app(RequestWithdrawal::class)->handle(fundedPlayer(100_000), '500');
}

test('sends the payout to the player\'s number and records Safaricom\'s conversation IDs', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/b2c/*' => Http::response(['ConversationID' => 'AG_1', 'OriginatorConversationID' => 'orig-1', 'ResponseCode' => '0', 'ResponseDescription' => 'Accept the service request successfully.'])]);
    $withdrawal = queuedWithdrawal();

    app(PayOutWithdrawal::class)->handle($withdrawal);

    expect($withdrawal->fresh())
        ->status->toBe(WithdrawalStatus::Processing)
        ->conversation_id->toBe('AG_1')
        ->originator_conversation_id->toBe('orig-1')
        ->sent_at->not->toBeNull();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/b2c/') && $request['PartyB'] === '254712345678' && $request['Amount'] === 500);
});

test('a payout is only ever sent once, however many times the job runs', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/b2c/*' => Http::response(['ConversationID' => 'AG_1', 'OriginatorConversationID' => 'orig-1', 'ResponseCode' => '0'])]);
    $withdrawal = queuedWithdrawal();

    app(PayOutWithdrawal::class)->handle($withdrawal);
    app(PayOutWithdrawal::class)->handle($withdrawal);

    Http::assertSentCount(2);
});

test('a payout Safaricom refuses outright returns the money', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/b2c/*' => Http::response(['errorCode' => '400.002.02', 'errorMessage' => 'Bad Request - Invalid PartyB'], 400)]);
    $withdrawal = queuedWithdrawal();

    app(PayOutWithdrawal::class)->handle($withdrawal);

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Failed)
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(100_000);
});

test('a payout with no clear answer stays processing and keeps the money held', function (Closure $fake) {
    fakeMpesa();
    $fake();
    $withdrawal = queuedWithdrawal();

    app(PayOutWithdrawal::class)->handle($withdrawal);

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Processing)
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(50_000);
})->with([
    'server error' => [fn () => Http::fake(['sandbox.safaricom.co.ke/mpesa/b2c/*' => Http::response(['errorCode' => '500.003.1001', 'errorMessage' => 'Internal Server Error'], 500)])],
    'connection lost' => [fn () => Http::fake(['sandbox.safaricom.co.ke/mpesa/b2c/*' => Http::failedConnection()])],
]);

test('payouts that cannot be sent because M-Pesa is not configured return the money', function () {
    config(['mpesa.callback_secret' => null]);
    $withdrawal = queuedWithdrawal();

    app(PayOutWithdrawal::class)->handle($withdrawal);

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Failed)
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(100_000);
});
