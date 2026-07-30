<?php

use App\Services\Generation\ModelQueueCoordinator;
use App\Services\Generation\QueueWindowBudget;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hairvision:queue-window
    {connection? : Queue connection name; defaults to QUEUE_CONNECTION}
    {--queue=default : Queue names to process}
    {--duration=58 : Total worker window in seconds}
    {--interval=1 : Delay while waiting for new jobs in seconds}
    {--timeout=1020 : Worker timeout in seconds}
    {--worker=1 : Stable worker lane identifier}', function (): int {
    $connection = (string) ($this->argument('connection') ?: config('queue.default'));
    $duration = app(QueueWindowBudget::class)->resolve((int) $this->option('duration'));
    $interval = max(1, (int) $this->option('interval'));
    $timeout = max(1, (int) $this->option('timeout'));
    $retryAfter = (int) config("queue.connections.$connection.retry_after", 0);

    if (! in_array(config("queue.connections.$connection.driver"), ['database', 'redis'], true)) {
        $this->error('The cPanel queue window requires a database or Redis queue connection.');

        return Command::FAILURE;
    }

    if ($retryAfter <= $timeout) {
        $this->error("Queue retry_after ($retryAfter) must be greater than worker timeout ($timeout).");

        return Command::FAILURE;
    }

    config([
        'hairvision.image_queue.worker_timeout' => $timeout,
    ]);

    $exitCode = Artisan::call('hairvision:worker:run', [
        'slot' => max(1, (int) $this->option('worker')),
        '--worker-id' => (string) Str::uuid(),
        '--mode' => 'window',
        '--connection' => $connection,
        '--queue' => (string) $this->option('queue'),
        '--max-time' => $duration,
    ]);

    $this->output->write(Artisan::output());

    return $exitCode;
})->purpose('Process HairVision queued jobs repeatedly inside one cron minute.');

/*
|--------------------------------------------------------------------------
| Queue Worker Scheduler
|--------------------------------------------------------------------------
|
| On shared cPanel hosting, explicitly enable the cron worker and run Laravel's
| scheduler every minute. Keep this disabled when Horizon is running.
|
*/

if (config('hairvision.queue_worker.cpanel_cron_enabled')) {
    Schedule::command('hairvision:workers:ensure')
        ->everyMinute()
        ->name('hairvision-image-workers-watchdog')
        ->withoutOverlapping(1);

    Schedule::call(function (): void {
        $queue = app(ModelQueueCoordinator::class);
        $queue->recoverStaleReservations();
        $queue->dispatchAllAvailable();
    })
        ->everyMinute()
        ->name('hairvision-generation-queue-coordinator')
        ->withoutOverlapping(5);

    Schedule::command('hairvision:generations:reconcile')
        ->everyMinute()
        ->name('hairvision-generation-reconcile')
        ->withoutOverlapping(5);

    Schedule::command('hairvision:generations:cleanup')
        ->dailyAt('03:30')
        ->name('hairvision-generation-cleanup')
        ->withoutOverlapping(120);

    Schedule::command('hairvision:queue-health --json')
        ->everyFiveMinutes()
        ->name('hairvision-image-queue-health')
        ->withoutOverlapping(5);

    $processes = config('hairvision.image_queue.worker_mode') === 'window'
        ? max(1, (int) config('hairvision.queue_worker.processes', 1))
        : 0;

    for ($worker = 1; $worker <= $processes; $worker++) {
        Schedule::command('hairvision:queue-window', [
            (string) config('hairvision.queue_worker.cpanel_connection', config('queue.default')),
            '--queue' => (string) config('hairvision.queue_worker.queues', 'ai-generation,default'),
            '--duration' => max(1, (int) config('hairvision.queue_worker.window_seconds', 58)),
            '--interval' => max(1, (int) config('hairvision.queue_worker.sleep_seconds', 1)),
            '--timeout' => max(1, (int) config('hairvision.queue_worker.timeout_seconds', 1020)),
            '--worker' => $worker,
        ])
            ->everyMinute()
            ->name('hairvision-queue-worker-'.$worker)
            ->runInBackground()
            ->withoutOverlapping(max(1, (int) config('hairvision.queue_worker.overlap_expiration_minutes', 30)));
    }
}
