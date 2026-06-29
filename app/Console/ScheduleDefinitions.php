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

        $schedule->command('exchange-rates:sync-kgs-banks --started-by-type=system --started-from=scheduler')
            ->description('Sync MBank and Obank exchange rates')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        $schedule->command('hh:poll-resume-analysis')
            ->description('Poll HH resume OpenAI analysis batches')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        $schedule->command('nsi:sgr-sync --mode=list --limit=1000 --max-pages=20 --pause-ms=300 --error-pause-ms=10000 --max-retries=5 --started-by-type=system --started-from=scheduler')
            ->description('Sync EAEU NSI SGR list')
            ->cron('5,20,35,50 * * * *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        $schedule->command('nsi:sgr-sync --mode=details --detail-limit=1000 --refresh-active-after-hours=24 --pause-ms=300 --error-pause-ms=10000 --max-retries=5 --started-by-type=system --started-from=scheduler')
            ->description('Sync EAEU NSI SGR details')
            ->cron('2,12,22,32,42,52 * * * *')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/scheduler.log'));
    }
}
