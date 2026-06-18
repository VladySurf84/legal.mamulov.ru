<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleDefinitions
{
    public static function define(Schedule $schedule): void
    {
        $schedule->command('tinkoff:sync-bank --days=5')
            ->description('Sync Tinkoff bank accounts and transactions')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    }
}
