<?php

use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Admin;
use App\Models\Withdrawal;
use App\Wallet\Ledger;
use App\Wallet\Withdrawals\CheckWithdrawalStatus;
use App\Wallet\Withdrawals\RequestWithdrawal;
use App\Wallet\Withdrawals\ReviewWithdrawal;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/**
 * A KES 25,000 withdrawal awaiting approval, from a player who had KES 30,000.
 */
function largeWithdrawal(): Withdrawal
{
    Queue::fake([SendWithdrawalPayout::class]);

    return app(RequestWithdrawal::class)->handle(fundedPlayer(3_000_000), '25000');
}

test('approving queues the payout and records who approved it', function () {
    $withdrawal = largeWithdrawal();
    $admin = Admin::factory()->create();

    app(ReviewWithdrawal::class)->approve($withdrawal, $admin);

    expect($withdrawal->fresh())
        ->status->toBe(WithdrawalStatus::Queued)
        ->reviewed_by_admin_id->toBe($admin->id);
    Queue::assertPushed(SendWithdrawalPayout::class);
    expect(Activity::query()->where('event', 'withdrawal-approved')->sole()->causer->is($admin))->toBeTrue();
});

test('rejecting returns the money and records the reason', function () {
    $withdrawal = largeWithdrawal();

    app(ReviewWithdrawal::class)->reject($withdrawal, Admin::factory()->create(), 'Documents requested');

    expect($withdrawal->fresh())
        ->status->toBe(WithdrawalStatus::Rejected)
        ->review_note->toBe('Documents requested')
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(3_000_000);
    expect(Activity::query()->where('event', 'withdrawal-rejected')->sole()->properties->get('reason'))->toBe('Documents requested');
});

test('a withdrawal can only be approved or rejected while it awaits approval', function () {
    $withdrawal = largeWithdrawal();
    $admin = Admin::factory()->create();
    app(ReviewWithdrawal::class)->reject($withdrawal, $admin, 'No');

    expect(fn () => app(ReviewWithdrawal::class)->approve($withdrawal, $admin))->toThrow(ValidationException::class);
    expect(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(3_000_000);
});

test('finance settles a payout M-Pesa never answered for, by hand', function (string $outcome, WithdrawalStatus $status, int $balance) {
    $withdrawal = largeWithdrawal();
    $withdrawal->update(['status' => WithdrawalStatus::Processing, 'sent_at' => now()->subHour()]);
    $admin = Admin::factory()->create();

    $outcome === 'paid'
        ? app(ReviewWithdrawal::class)->markPaid($withdrawal, $admin, 'TKP1Q2R3S4', 'Confirmed in the portal')
        : app(ReviewWithdrawal::class)->markFailed($withdrawal, $admin, 'Not in the portal');

    expect($withdrawal->fresh()->status)->toBe($status)
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe($balance);
})->with([
    'paid' => ['paid', WithdrawalStatus::Completed, 500_000],
    'failed' => ['failed', WithdrawalStatus::Failed, 3_000_000],
]);

test('a status check that finds the payout completed marks it paid', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/transactionstatus/*' => Http::response(['ConversationID' => 'AG_STATUS', 'OriginatorConversationID' => 'orig-status', 'ResponseCode' => '0'])]);
    $withdrawal = largeWithdrawal();
    $withdrawal->update(['status' => WithdrawalStatus::Processing, 'originator_conversation_id' => 'orig-1', 'sent_at' => now()->subHour()]);

    app(CheckWithdrawalStatus::class)->request($withdrawal, Admin::factory()->create());

    $this->postJson(mpesaCallbackUrl('b2c/status/result'), mpesaResult('AG_STATUS', parameters: ['TransactionStatus' => 'Completed', 'ReceiptNo' => 'TKP1Q2R3S4']))->assertOk();

    expect($withdrawal->fresh())
        ->status->toBe(WithdrawalStatus::Completed)
        ->mpesa_receipt->toBe('TKP1Q2R3S4');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'transactionstatus') && $request['OriginalConversationID'] === 'orig-1');
});

test('a status check that does not find the payout never returns the money on its own', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/transactionstatus/*' => Http::response(['ConversationID' => 'AG_STATUS', 'ResponseCode' => '0'])]);
    $withdrawal = largeWithdrawal();
    $withdrawal->update(['status' => WithdrawalStatus::Processing, 'sent_at' => now()->subHour()]);
    app(CheckWithdrawalStatus::class)->request($withdrawal, Admin::factory()->create());

    $this->postJson(mpesaCallbackUrl('b2c/status/result'), mpesaResult('AG_STATUS', resultCode: 2001))->assertOk();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Processing)
        ->and(app(Ledger::class)->balanceOf($withdrawal->user))->toBe(500_000);
});
