<?php

use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\Queue;

test('the wallet returns 200 with the balance, bounds and Paybill numbers', function () {
    fakeMpesa();
    $player = fundedPlayer(100_000);

    $response = $this->actingAs($player, 'sanctum')->getJson(route('api.v1.wallet.show'));

    $response->assertOk()
        ->assertJsonPath('balance_cents', 100_000)
        ->assertJsonPath('deposit_allowance_cents', null)
        ->assertJsonPath('can_deposit', true)
        ->assertJsonPath('can_withdraw', true)
        ->assertJsonPath('min_deposit', 10)
        ->assertJsonPath('paybill', ['business_number' => '600000', 'account_number' => '0712345678']);
});

test('the wallet returns no Paybill numbers when Paybill is not set up', function () {
    config(['mpesa.c2b.short_code' => null]);

    $this->actingAs(fundedPlayer(), 'sanctum')->getJson(route('api.v1.wallet.show'))
        ->assertOk()
        ->assertJsonPath('paybill', null);
});

test('wallet transactions return 200 with only my own entries, newest first', function () {
    $player = fundedPlayer(100_000);
    bettor();
    betOn($player, [[pricedFixture(), 'home']]);

    $response = $this->actingAs($player, 'sanctum')->getJson(route('api.v1.wallet.transactions'));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount_cents', -10_000)
        ->assertJsonPath('data.0.balance_after_cents', 90_000)
        ->assertJsonPath('data.1.amount_cents', 100_000);
});

test('depositing returns 201 with a pending deposit after sending the M-Pesa prompt', function () {
    fakeMpesa();
    fakeStkPushAccepted();
    $player = User::factory()->create(['phone' => '254712345678']);

    $response = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.deposits.store'), ['amount' => 500]);

    $response->assertCreated()
        ->assertJsonPath('data.is_pending', true)
        ->assertJsonPath('data.amount_cents', 50_000);
    expect(MpesaDeposit::sole())
        ->user_id->toBe($player->id)
        ->checkout_request_id->toBe('ws_CO_1');
});

test('depositing below the minimum returns 422 and sends no prompt', function () {
    fakeMpesa();
    fakeStkPushAccepted();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson(route('api.v1.deposits.store'), ['amount' => 9])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount' => 'The minimum deposit is KES 10.']);

    expect(MpesaDeposit::count())->toBe(0);
});

test('depositing on a suspended account returns 422', function () {
    fakeMpesa();
    fakeStkPushAccepted();

    $this->actingAs(User::factory()->create(['status' => UserStatus::Suspended]), 'sanctum')
        ->postJson(route('api.v1.deposits.store'), ['amount' => 500])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount' => 'Your account is suspended. Please contact support.']);

    expect(MpesaDeposit::count())->toBe(0);
});

test('a deposit returns 200 for its owner', function () {
    $deposit = MpesaDeposit::factory()->create();

    $this->actingAs($deposit->user, 'sanctum')->getJson(route('api.v1.deposits.show', $deposit))
        ->assertOk()
        ->assertJsonPath('data.id', $deposit->id)
        ->assertJsonPath('data.status', 'pending');
});

test('another player\'s deposit returns 404', function () {
    $deposit = MpesaDeposit::factory()->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson(route('api.v1.deposits.show', $deposit))
        ->assertNotFound();
});

test('withdrawing returns 201, holds the money and queues the payout', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(100_000);

    $response = $this->actingAs($player, 'sanctum')->postJson(route('api.v1.withdrawals.store'), ['amount' => 500]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.amount_cents', 50_000);
    expect(app(Ledger::class)->balanceOf($player))->toBe(50_000);
    Queue::assertPushed(SendWithdrawalPayout::class);
});

test('withdrawing more than the balance returns 422 and moves no money', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $player = fundedPlayer(10_000);

    $this->actingAs($player, 'sanctum')
        ->postJson(route('api.v1.withdrawals.store'), ['amount' => 500])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount' => 'You can withdraw up to KES 100.00.']);

    expect(Withdrawal::count())->toBe(0)
        ->and(app(Ledger::class)->balanceOf($player))->toBe(10_000);
    Queue::assertNothingPushed();
});

test('my withdrawals return 200 with only my own', function () {
    $player = User::factory()->create();
    $own = Withdrawal::factory()->for($player)->create(['status' => WithdrawalStatus::Queued]);
    Withdrawal::factory()->create();

    $this->actingAs($player, 'sanctum')->getJson(route('api.v1.withdrawals.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});

test('wallet endpoints return 401 without a token', function () {
    $this->getJson(route('api.v1.wallet.show'))->assertUnauthorized();
    $this->postJson(route('api.v1.deposits.store'), ['amount' => 500])->assertUnauthorized();
    $this->postJson(route('api.v1.withdrawals.store'), ['amount' => 500])->assertUnauthorized();
});
