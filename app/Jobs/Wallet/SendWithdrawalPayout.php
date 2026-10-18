<?php

namespace App\Jobs\Wallet;

use App\Models\Withdrawal;
use App\Wallet\Withdrawals\PayOutWithdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pays one withdrawal out through M-Pesa. Never retried: a retry after an unclear answer could pay twice.
 * Dispatched only after the request's database transaction commits.
 */
class SendWithdrawalPayout implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public Withdrawal $withdrawal)
    {
        $this->afterCommit();
    }

    public function handle(PayOutWithdrawal $payOutWithdrawal): void
    {
        $payOutWithdrawal->handle($this->withdrawal);
    }
}
