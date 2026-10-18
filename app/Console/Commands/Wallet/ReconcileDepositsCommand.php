<?php

namespace App\Console\Commands\Wallet;

use App\Wallet\Deposits\ReconcilePendingDeposits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('wallet:reconcile-deposits')]
#[Description('Check STK deposits whose M-Pesa callback has not arrived, and expire ones that never will')]
class ReconcileDepositsCommand extends Command
{
    public function handle(ReconcilePendingDeposits $reconcilePendingDeposits): int
    {
        $counts = $reconcilePendingDeposits->handle();

        $this->info("Completed {$counts['completed']}, closed {$counts['closed']}, expired {$counts['expired']}, still waiting {$counts['waiting']}.");

        return self::SUCCESS;
    }
}
