<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleDefinitions
{
    public static function define(Schedule $schedule): void
    {
        $schedule->command('tinkoff:sync-bank --days=5 --started-by-type=system --started-from=scheduler')
            ->description('Sync Tinkoff bank accounts and transactions')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    }
}
