<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Queue Worker Scheduler
|--------------------------------------------------------------------------
|
| On shared cPanel hosting, run Laravel scheduler via Cron every minute.
| The scheduler will process one queued job at a time and prevent overlapping.
|
*/

Schedule::command('queue:work redis --queue=default --once --timeout=300 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(10);

// For cpanel
/*
Schedule::command('queue:work database --queue=default --once --timeout=300 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(10);
*/