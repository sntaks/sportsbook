<?php

use App\Http\Controllers\MpesaCallbackController;
use App\Http\Middleware\VerifyMpesaCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
