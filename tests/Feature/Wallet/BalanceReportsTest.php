<?php

use App\Enums\MpesaBalanceReportStatus;
use App\Models\Admin;
use App\Models\MpesaBalanceReport;
use App\Wallet\Mpesa\BalanceReports;
use Illuminate\Support\Facades\Http;

test('a balance check is stored, then filled in by Safaricom\'s answer', function () {
    fakeMpesa();
    config(['mpesa.c2b.initiator_name' => 'testapi', 'mpesa.c2b.security_credential' => 'initiator-password']);
    Http::fake(['sandbox.safaricom.co.ke/mpesa/accountbalance/*' => Http::response(['ConversationID' => 'AG_BAL', 'OriginatorConversationID' => 'orig-bal', 'ResponseCode' => '0'])]);

    $report = app(BalanceReports::class)->request('paybill', Admin::factory()->create());

    expect($report->status)->toBe(MpesaBalanceReportStatus::Requested);

    $this->postJson(mpesaCallbackUrl('balance/result'), mpesaResult('AG_BAL', parameters: [
        'AccountBalance' => 'Working Account|KES|46713.00|46713.00|0.00|0.00&Utility Account|KES|1250.50|1250.50|0.00|0.00',
        'BOCompletedTime' => 20261018101500,
    ]))->assertOk();

    expect($report->fresh())
        ->status->toBe(MpesaBalanceReportStatus::Received)
        ->balances->toEqual([
            ['name' => 'Working Account', 'currency' => 'KES', 'available' => 46713.0],
            ['name' => 'Utility Account', 'currency' => 'KES', 'available' => 1250.5],
        ]);
});

test('a failed balance answer is recorded as failed', function () {
    fakeMpesa();
    $report = MpesaBalanceReport::create(['account' => 'b2c', 'conversation_id' => 'AG_BAL', 'status' => MpesaBalanceReportStatus::Requested]);

    $this->postJson(mpesaCallbackUrl('balance/result'), mpesaResult('AG_BAL', resultCode: 2001))->assertOk();

    expect($report->fresh()->status)->toBe(MpesaBalanceReportStatus::Failed);
});
