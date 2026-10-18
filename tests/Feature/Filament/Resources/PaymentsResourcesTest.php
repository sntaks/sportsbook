<?php

use App\Enums\AdminRole;
use App\Enums\MpesaDepositChannel;
use App\Enums\MpesaDepositStatus;
use App\Enums\WithdrawalStatus;
use App\Filament\Pages\Reconciliation;
use App\Filament\Pages\Reconciliation\AccountBalances;
use App\Filament\Pages\Reconciliation\DailyTotals;
use App\Filament\Pages\Reconciliation\NeedsAttention;
use App\Filament\Resources\Deposits\Pages\ManageDeposits;
use App\Filament\Resources\Withdrawals\Pages\ManageWithdrawals;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Jobs\Wallet\SendWithdrawalPayout;
use App\Models\Admin;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Wallet\Deposits\CompleteDeposit;
use App\Wallet\Ledger;
use App\Wallet\Withdrawals\RequestWithdrawal;
use Database\Seeders\AdminRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

test('finance approves a large withdrawal from the queue', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    $withdrawal = app(RequestWithdrawal::class)->handle(fundedPlayer(3_000_000), '25000');

    Livewire::test(ManageWithdrawals::class)
        ->assertCanSeeTableRecords([$withdrawal])
        ->callAction(TestAction::make('approve')->table($withdrawal))
        ->assertNotified();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Queued);
    Queue::assertPushed(SendWithdrawalPayout::class);
});

test('support sees the withdrawals queue but cannot approve', function () {
    Queue::fake([SendWithdrawalPayout::class]);
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $withdrawal = app(RequestWithdrawal::class)->handle(fundedPlayer(3_000_000), '25000');

    Livewire::test(ManageWithdrawals::class)
        ->assertCanSeeTableRecords([$withdrawal])
        ->assertActionHidden(TestAction::make('approve')->table($withdrawal));
});

test('mark paid and mark failed only appear once a payout has had no answer for 30 minutes', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    $recent = Withdrawal::factory()->processing(minutesAgo: 5)->create();
    $stuck = Withdrawal::factory()->processing(minutesAgo: 45)->create();

    Livewire::test(ManageWithdrawals::class)
        ->assertActionHidden(TestAction::make('markFailed')->table($recent))
        ->assertActionVisible(TestAction::make('markFailed')->table($stuck));
});

test('finance assigns an unmatched Paybill payment to the right player, once', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    $player = User::factory()->create(['phone' => '254712345678']);
    $deposit = MpesaDeposit::factory()->create([
        'user_id' => null,
        'channel' => MpesaDepositChannel::Paybill,
        'status' => MpesaDepositStatus::Unmatched,
        'phone' => '2547 ***** 678',
        'amount' => 25_000,
        'account_reference' => '0712345687',
        'mpesa_receipt' => 'TJK9Z8Y7X6',
        'checkout_request_id' => null,
    ]);

    Livewire::test(ManageDeposits::class)
        ->callAction(TestAction::make('credit')->table($deposit), data: ['player_id' => $player->id, 'note' => 'Player typed two digits the wrong way round'])
        ->assertNotified();

    expect($deposit->fresh())
        ->status->toBe(MpesaDepositStatus::Completed)
        ->user_id->toBe($player->id)
        ->resolution_note->toBe('Player typed two digits the wrong way round')
        ->and(app(Ledger::class)->balanceOf($player))->toBe(25_000);

    Livewire::test(ManageDeposits::class)
        ->assertActionHidden(TestAction::make('credit')->table($deposit));
});

test('a mismatched deposit is credited with what M-Pesa actually received', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    $deposit = MpesaDeposit::factory()->create(['status' => MpesaDepositStatus::Mismatch, 'amount' => 50_000, 'paid_amount' => 500, 'mpesa_receipt' => 'TJK1A2B3C4']);

    Livewire::test(ManageDeposits::class)
        ->callAction(TestAction::make('credit')->table($deposit), data: ['note' => 'Player paid KES 5 by mistake'])
        ->assertNotified();

    expect(app(Ledger::class)->balanceOf($deposit->user))->toBe(500);
});

test('dismissing closes the payment without crediting anyone', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    $deposit = MpesaDeposit::factory()->create(['status' => MpesaDepositStatus::Mismatch, 'paid_amount' => 500]);

    Livewire::test(ManageDeposits::class)
        ->callAction(TestAction::make('dismiss')->table($deposit), data: ['note' => 'Refunded in the portal'])
        ->assertNotified();

    expect($deposit->fresh()->status)->toBe(MpesaDepositStatus::Dismissed)
        ->and(app(Ledger::class)->balanceOf($deposit->user))->toBe(0);
});

test('the reconciliation page is for finance and managers only', function (AdminRole $role, int $status) {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole($role)->create(), 'admin');

    $this->get(Reconciliation::getUrl())->assertStatus($status);
})->with([
    'finance' => [AdminRole::Finance, 200],
    'manager' => [AdminRole::Manager, 200],
    'support' => [AdminRole::Support, 403],
]);

test('the reconciliation widgets show the day\'s figures and what needs attention', function () {
    $this->travelTo(now()->setTimezone('Africa/Nairobi')->setTime(12, 0)->setTimezone('UTC'));
    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin');
    app(CompleteDeposit::class)->handle(MpesaDeposit::factory()->create(['amount' => 75_000]), 'TJK1A2B3C4');
    MpesaDeposit::factory()->create(['status' => MpesaDepositStatus::Mismatch]);

    Livewire::test(DailyTotals::class)->assertSee('KES 750.00');
    Livewire::test(NeedsAttention::class)->assertSeeInOrder(['Mismatched deposits', '1']);
    Livewire::test(AccountBalances::class)->assertSee('Not checked yet');
});

test('a trader cannot open the withdrawals queue', function () {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole(AdminRole::Trader)->create(), 'admin');

    $this->get(WithdrawalResource::getUrl('index'))->assertForbidden();
});
