<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\BetController;
use App\Http\Controllers\Api\V1\BookingCodeController;
use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\DepositLimitController;
use App\Http\Controllers\Api\V1\ExclusionController;
use App\Http\Controllers\Api\V1\FixtureController;
use App\Http\Controllers\Api\V1\JackpotController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\RegistrationController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SlipController;
use App\Http\Controllers\Api\V1\SportController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use App\Http\Controllers\MpesaCallbackController;
use App\Http\Middleware\VerifyMpesaCallback;
use Illuminate\Support\Facades\Route;

// The mobile app's API. Players authenticate with a Sanctum token from /auth/login or /auth/register/verify.
Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [RegistrationController::class, 'start'])->name('register');
        Route::post('register/resend', [RegistrationController::class, 'resend'])->name('register.resend');
        Route::post('register/verify', [RegistrationController::class, 'verify'])->name('register.verify');
        Route::post('login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');
        Route::post('forgot-password', [PasswordResetController::class, 'store'])->name('forgot-password');
        Route::post('reset-password', [PasswordResetController::class, 'update'])->name('reset-password');
        Route::post('logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum')->name('logout');
    });

    Route::get('sports', [SportController::class, 'index'])->name('sports.index');
    Route::get('fixtures', [FixtureController::class, 'index'])->name('fixtures.index');
    Route::get('fixtures/live', [FixtureController::class, 'live'])->name('fixtures.live');
    Route::get('fixtures/search', [FixtureController::class, 'search'])->name('fixtures.search');
    Route::get('fixtures/{fixture}', [FixtureController::class, 'show'])->whereNumber('fixture')->name('fixtures.show');

    Route::post('slip/quote', [SlipController::class, 'quote'])->name('slip.quote');
    Route::post('booking-codes', [BookingCodeController::class, 'store'])->middleware('throttle:booking-codes-create')->name('booking-codes.store');
    Route::get('booking-codes/{code}', [BookingCodeController::class, 'show'])->middleware('throttle:booking-codes-show')->name('booking-codes.show');

    Route::get('jackpots/current', [JackpotController::class, 'current'])->name('jackpots.current');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AccountController::class, 'show'])->name('me');
        Route::put('me/odds-change-policy', [AccountController::class, 'updateOddsPolicy'])->name('me.odds-change-policy');

        Route::get('bets', [BetController::class, 'index'])->name('bets.index');
        Route::post('bets', [BetController::class, 'store'])->name('bets.store');
        Route::get('bets/{reference}', [BetController::class, 'show'])->name('bets.show');

        Route::post('jackpots/{jackpot}/entries', [JackpotController::class, 'enter'])->whereNumber('jackpot')->name('jackpots.entries.store');
        Route::get('jackpot-entries', [JackpotController::class, 'entries'])->name('jackpot-entries.index');

        Route::get('wallet', [WalletController::class, 'show'])->name('wallet.show');
        Route::get('wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');
        Route::post('deposits', [DepositController::class, 'store'])->name('deposits.store');
        Route::get('deposits/{deposit}', [DepositController::class, 'show'])->whereNumber('deposit')->name('deposits.show');
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');

        Route::get('deposit-limits', [DepositLimitController::class, 'index'])->name('deposit-limits.index');
        Route::put('deposit-limits/{period}', [DepositLimitController::class, 'update'])->name('deposit-limits.update');
        Route::post('exclusions', [ExclusionController::class, 'store'])->name('exclusions.store');
    });
});

// Safaricom callbacks. The {secret} segment is MPESA_CALLBACK_SECRET; VerifyMpesaCallback answers 404 otherwise.
Route::prefix('mpesa/{secret}')
    ->middleware(VerifyMpesaCallback::class)
    ->name('mpesa.callbacks.')
    ->controller(MpesaCallbackController::class)
    ->group(function () {
        Route::post('stk', 'stk')->name('stk');
        Route::post('c2b/validation', 'paybillValidation')->name('paybill-validation');
        Route::post('c2b/confirmation', 'paybillConfirmation')->name('paybill-confirmation');
        Route::post('b2c/result', 'payoutResult')->name('payout-result');
        Route::post('b2c/status/result', 'payoutStatusResult')->name('payout-status-result');
        Route::post('balance/result', 'balanceResult')->name('balance-result');
        Route::post('{queue}/timeout', 'queueTimeout')
            ->where('queue', 'b2c|b2c/status|balance|reversal')
            ->name('queue-timeout');
    });
