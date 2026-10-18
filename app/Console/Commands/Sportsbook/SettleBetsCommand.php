<?php

namespace App\Console\Commands\Sportsbook;

use App\Betting\Settlement\FetchResults;
use App\Betting\Settlement\VoidPostponedLegs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:settle')]
#[Description('Void legs on postponed matches and settle finished matches from the scores feed (credits only when bets are waiting)')]
class SettleBetsCommand extends Command
{
    public function handle(VoidPostponedLegs $voidPostponedLegs, FetchResults $fetchResults): int
    {
        $voided = $voidPostponedLegs->handle();
        $fetched = $fetchResults->handle();

        $this->info("Voided {$voided} postponed legs. Fetched scores for {$fetched['competitions']} competitions and recorded {$fetched['results']} results.");

        return self::SUCCESS;
    }
}
