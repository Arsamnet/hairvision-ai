<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hairvision:queue-window
    {connection=redis : Queue connection name}
    {--queue=default : Queue names to process}
    {--duration=55 : Total window duration in seconds}
    {--interval=10 : Delay between queue checks in seconds}
    {--timeout=300 : Worker timeout in seconds}', function () {
        $connection = (string) $this->argument('connection');
        $duration = max(1, (int) $this->option('duration'));
        $interval = max(1, (int) $this->option('interval'));
        $deadline = microtime(true) + $duration;

        do {
            Artisan::call('queue:work', [
                'connection' => $connection,
                '--queue' => (string) $this->option('queue'),
                '--stop-when-empty' => true,
                '--timeout' => max(1, (int) $this->option('timeout')),
                '--tries' => 1,
            ]);

            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                break;
            }

            sleep((int) min($interval, $remaining));
        } while (microtime(true) < $deadline);
    })->purpose('Process HairVision queued jobs repeatedly inside one cron minute.');

/*
|--------------------------------------------------------------------------
| Queue Worker Scheduler
|--------------------------------------------------------------------------
|
| On shared cPanel hosting, run Laravel scheduler via Cron every minute.
| The scheduler will process one queued job at a time and prevent overlapping.
|
*/

Schedule::command('hairvision:queue-window redis --queue=default --duration=55 --interval=10 --timeout=300')
    ->everyMinute()
    ->withoutOverlapping(10);

// For cpanel
/*
Schedule::command('hairvision:queue-window database --queue=default --duration=55 --interval=10 --timeout=300')
    ->everyMinute()
    ->withoutOverlapping(10);
*/
