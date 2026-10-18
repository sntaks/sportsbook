<?php

use App\Enums\MpesaDepositStatus;
use App\Enums\WalletTransactionType;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Wallet\Ledger;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('guests are sent to log in', function () {
    $this->get(route('wallet'))->assertRedirect(route('login'));
});

test('shows the balance and only the player\'s own transactions', function () {
    $user = User::factory()->create();
    $ledger = app(Ledger::class);
    $ledger->post($user, WalletTransactionType::Deposit, 125_050, 'test:1', description: 'M-Pesa deposit TJK1A2B3C4');
    $ledger->post(User::factory()->create(), WalletTransactionType::Deposit, 99_900, 'test:2', description: 'M-Pesa deposit OTHER12345');

    $this->actingAs($user)
        ->get(route('wallet'))
        ->assertOk()
        ->assertSee('KES 1,250.50')
        ->assertSee('TJK1A2B3C4')
        ->assertDontSee('OTHER12345');
});

test('the header shows a logged-in player their balance', function () {
    $user = User::factory()->create();
    app(Ledger::class)->post($user, WalletTransactionType::Deposit, 50_000, 'test:1');

    $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('KES 500.00');
});

test('depositing sends the push and shows that the page is waiting on the phone', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => Http::response(['ResponseCode' => '0', 'MerchantRequestID' => 'MR-1', 'CheckoutRequestID' => 'ws_CO_1'])]);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::sportsbook.wallet')
        ->call('setAmount', 500)
        ->call('deposit')
        ->assertHasNoErrors()
        ->assertSet('amount', '')
        ->assertSee('Check your phone')
        ->assertSee('KES 500.00');

    expect(MpesaDeposit::sole()->status)->toBe(MpesaDepositStatus::Pending);
});

test('a refused deposit shows why', function () {
    fakeMpesa();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::sportsbook.wallet')
        ->set('amount', '5')
        ->call('deposit')
        ->assertHasErrors(['amount' => 'The minimum deposit is KES 10.']);
});

test('once the deposit completes, the page says so and tells the header to refresh', function () {
    fakeMpesa();
    Http::fake(['sandbox.safaricom.co.ke/mpesa/stkpush/*' => Http::response(['ResponseCode' => '0', 'MerchantRequestID' => 'MR-1', 'CheckoutRequestID' => 'ws_CO_1'])]);
    $user = User::factory()->create(['phone' => '254712345678']);
    $this->actingAs($user);

    $page = Livewire::test('pages::sportsbook.wallet')->set('amount', '500')->call('deposit');

    $this->postJson(mpesaCallbackUrl('stk'), stkCallback('ws_CO_1'))->assertOk();

    $page->call('checkDeposit')
        ->assertDispatched('wallet-updated')
        ->assertSee('KES 500.00 deposited')
        ->assertSee('TJK1A2B3C4');
});

test('a player cannot watch someone else\'s deposit', function () {
    $other = MpesaDeposit::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::sportsbook.wallet')
        ->set('depositId', $other->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('shows Paybill instructions with the player\'s number as the account', function () {
    config(['mpesa.c2b.short_code' => '600000']);

    $this->actingAs(User::factory()->create(['phone' => '254712345678']))
        ->get(route('wallet'))
        ->assertSee('600000')
        ->assertSee('0712345678');
});
