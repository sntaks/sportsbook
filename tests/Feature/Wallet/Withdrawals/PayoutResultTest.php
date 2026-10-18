<?php

use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Withdrawal;
use App\Wallet\Ledger;
use App\Wallet\Withdrawals\RequestWithdrawal;
use Illuminate\Support\Facades\Queue;

/**
 * A KES 500 withdrawal sent to M-Pesa, from a player who had KES 1,000.
 */
function sentWithdrawal(?string $conversationId = 'AG_1'): Withdrawal
{
    Queue::fake([SendWithdrawalPayout::class]);
    $withdrawal = app(RequestWithdrawal::class)->handle(fundedPlayer(100_000), '500');
    $withdrawal->update(['status' => WithdrawalStatus::Processing, 'conversation_id' => $conversationId, 'originator_conversation_id' => $conversationId === null ? null : 'orig-1', 'sent_at' => now()]);

    return $withdrawal;
}

function paidParameters(string $phone = '254712345678', int $amount = 500): array
{
    return ['TransactionAmount' => $amount, 'TransactionReceipt' => 'TKP1Q2R3S4', 'ReceiverPartyPublicName' => "{$phone} - Jane Doe"];
}

test('a successful result marks the withdrawal paid with its receipt', function () {
    fakeMpesa();
    $withdrawal = sentWithdrawal();

    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_1', parameters: paidParameters()))
        ->assertOk()
        ->assertExactJson(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

    expect($withdrawal->fresh())
        ->status->toBe(WithdrawalStatus::Completed)
        ->mpesa_receipt->toBe('TKP1Q2R3S4')
        ->completed_at->not->toBeNull()
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(50_000);
});

test('a failed result returns the money once, however often it is repeated', function () {
    fakeMpesa();
    $withdrawal = sentWithdrawal();

    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_1', resultCode: 2001))->assertOk();
    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_1', resultCode: 2001))->assertOk();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Failed)
        ->and($withdrawal->fresh()->result_code)->toBe('2001')
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(100_000);
});

test('a success after the money was returned is flagged, never paid back twice', function () {
    fakeMpesa();
    $mpesaLog = spyOnMpesaLog();
    $withdrawal = sentWithdrawal();
    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_1', resultCode: 2001))->assertOk();

    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_1', parameters: paidParameters()))->assertOk();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Failed)
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(100_000);
    $mpesaLog->shouldHaveReceived('critical');
});

test('when Safaricom\'s reply was lost, the result is matched by number and amount', function () {
    fakeMpesa();
    $withdrawal = sentWithdrawal(conversationId: null);

    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_9', parameters: paidParameters()))->assertOk();

    expect($withdrawal->fresh())
        ->status->toBe(WithdrawalStatus::Completed)
        ->conversation_id->toBe('AG_9');
});

test('a lost-reply result that matches two withdrawals is left for finance', function () {
    fakeMpesa();
    $first = sentWithdrawal(conversationId: null);
    $second = Withdrawal::factory()->for($first->user)->create(['status' => WithdrawalStatus::Processing, 'phone' => '254712345678', 'amount' => 50_000]);

    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_9', parameters: paidParameters()))->assertOk();

    expect($first->fresh()->status)->toBe(WithdrawalStatus::Processing)
        ->and($second->fresh()->status)->toBe(WithdrawalStatus::Processing);
});

test('a queue timeout changes nothing', function (string $path) {
    fakeMpesa();
    $withdrawal = sentWithdrawal();

    $this->postJson(mpesaCallbackUrl($path), mpesaResult('AG_1', resultCode: 1))->assertOk();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Processing);
})->with(['b2c/timeout', 'b2c/status/timeout', 'balance/timeout']);

test('the stored result has the receiver\'s number and name redacted', function () {
    fakeMpesa();
    $withdrawal = sentWithdrawal();

    $this->postJson(mpesaCallbackUrl('b2c/result'), mpesaResult('AG_1', parameters: paidParameters()))->assertOk();

    expect(json_encode($withdrawal->fresh()->result_payload))->not->toContain('254712345678')->not->toContain('Jane Doe');
});

test('payout results with the wrong secret get a 404', function () {
    fakeMpesa();
    $withdrawal = sentWithdrawal();

    $this->postJson('/api/mpesa/wrong-secret-wrong-secret-wrong-secret-12/b2c/result', mpesaResult('AG_1', parameters: paidParameters()))->assertNotFound();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Processing);
});
