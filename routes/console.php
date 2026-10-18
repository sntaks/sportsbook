<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Free: competition list and fixtures, including kick-off changes and matches in play.
Schedule::command('sportsbook:sync-fixtures')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer();

// Paid: one credit per synced competition per market per region. Once a day on the free plan.
Schedule::command('sportsbook:sync-odds')
    ->dailyAt('09:00')
    ->timezone('Africa/Nairobi')
    ->withoutOverlapping(60)
    ->onOneServer();

// STK deposits whose callback never came: asks Safaricom how they ended, so no player's money gets stuck.
Schedule::command('wallet:reconcile-deposits')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();

// Free postponement voids every run; paid scores (2 credits) only for competitions with bets waiting on a result,
// at most every 12 hours each.
Schedule::command('sportsbook:settle')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('sportsbook:suspend-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
