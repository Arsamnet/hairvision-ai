<?php

namespace App\Providers;

use App\Services\Debug\DebugStepLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers project-wide debug step instrumentation for Laravel workflows.
 *
 * @since 1.0.0
 */
final class DebugStepServiceProvider extends ServiceProvider
{
    /**
     * Register model lifecycle behavior.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $this->listenForRoutes();
        $this->listenForDatabaseQueries();
        $this->listenForQueueWork();
        $this->listenForConsoleCommands();
        $this->listenForAuthentication();
        $this->listenForModelChanges();
        $this->listenForHttpClient();
    }

    /**
     * Execute the listen for routes operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForRoutes(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            DebugStepLogger::step($event->request, 'http.route', 'Route matched', [
                'route_name' => $event->route->getName(),
                'uri' => $event->route->uri(),
                'methods' => $event->route->methods(),
                'action' => $event->route->getActionName(),
            ]);
        });
    }

    /**
     * Execute the listen for database queries operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForDatabaseQueries(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (! (bool) config('hairvision.debug_steps.database_queries', true)) {
                return;
            }

            $slowQueryMs = (float) config('hairvision.debug_steps.slow_query_ms', 0);

            if ($slowQueryMs > 0 && $query->time < $slowQueryMs) {
                return;
            }

            DebugStepLogger::record('database.query', 'Query executed', [
                'connection' => $query->connectionName,
                'read_write_type' => $query->readWriteType,
                'time_ms' => round($query->time, 2),
                'sql' => self::redactSql($query->sql),
                'binding_count' => count($query->bindings),
            ]);
        });
    }

    /**
     * Execute the listen for queue work operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForQueueWork(): void
    {
        Event::listen(JobQueued::class, function (JobQueued $event): void {
            DebugStepLogger::record('queue.job', 'Job queued', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'job_id' => $event->id,
                'job' => self::queuedJobName($event->job),
                'delay' => $event->delay,
            ]);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            DebugStepLogger::record('queue.job', 'Job processing started', self::queueJobContext($event->connectionName, $event->job));
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            DebugStepLogger::record('queue.job', 'Job processed successfully', self::queueJobContext($event->connectionName, $event->job));
        });

        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            DebugStepLogger::record('queue.job', 'Job exception occurred', array_merge(
                self::queueJobContext($event->connectionName, $event->job),
                ['exception' => $event->exception],
            ));
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            DebugStepLogger::record('queue.job', 'Job failed', array_merge(
                self::queueJobContext($event->connectionName, $event->job),
                ['exception' => $event->exception],
            ));
        });
    }

    /**
     * Execute the listen for console commands operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForConsoleCommands(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            DebugStepLogger::record('console.command', 'Command started', [
                'command' => $event->command,
            ]);
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            DebugStepLogger::record('console.command', 'Command finished', [
                'command' => $event->command,
                'exit_code' => $event->exitCode,
            ]);
        });
    }

    /**
     * Execute the listen for authentication operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForAuthentication(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            DebugStepLogger::record('auth.event', 'User logged in', [
                'guard' => $event->guard,
                'user' => $event->user,
                'remember' => $event->remember,
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            DebugStepLogger::record('auth.event', 'User logged out', [
                'guard' => $event->guard,
                'user' => $event->user,
            ]);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            DebugStepLogger::record('auth.event', 'Authentication failed', [
                'guard' => $event->guard,
                'user' => $event->user,
                'credential_keys' => array_keys($event->credentials),
            ]);
        });
    }

    /**
     * Execute the listen for model changes operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForModelChanges(): void
    {
        Event::listen([
            'eloquent.created: *',
            'eloquent.updated: *',
            'eloquent.deleted: *',
            'eloquent.restored: *',
        ], function (string $event, array $payload): void {
            if (! (bool) config('hairvision.debug_steps.model_events', true)) {
                return;
            }

            $model = $payload[0] ?? null;

            if (! $model instanceof Model) {
                return;
            }

            DebugStepLogger::record('eloquent.model', 'Model lifecycle event fired', [
                'event' => self::modelEventName($event),
                'model' => $model::class,
                'id' => $model->getKey(),
                'uuid' => $model->getAttribute('uuid'),
                'tenant_id' => $model->getAttribute('tenant_id'),
                'exists' => $model->exists,
                'was_changed' => $model->wasChanged(),
                'changed' => array_values(array_diff(
                    array_keys($model->getChanges()),
                    ['password', 'remember_token', 'credentials', 'encrypted_credentials', 'code_hash'],
                )),
            ]);
        });
    }

    /**
     * Execute the listen for http client operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function listenForHttpClient(): void
    {
        Event::listen(RequestSending::class, function (RequestSending $event): void {
            DebugStepLogger::record('http.client', 'Outgoing HTTP request sending', self::httpClientContext($event->request));
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event): void {
            DebugStepLogger::record('http.client', 'Outgoing HTTP response received', self::httpClientContext($event->request) + [
                'status' => $event->response->status(),
                'successful' => $event->response->successful(),
            ]);
        });

        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event): void {
            DebugStepLogger::record('http.client', 'Outgoing HTTP connection failed', self::httpClientContext($event->request) + [
                'exception' => $event->exception,
            ]);
        });
    }

    /**
     * Execute the queue job context operation.
     *
     * @param  string  $connectionName  The connection name value.
     * @param  Job  $job  The job value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private static function queueJobContext(string $connectionName, Job $job): array
    {
        return [
            'connection' => $connectionName,
            'queue' => method_exists($job, 'getQueue') ? $job->getQueue() : null,
            'job_id' => $job->getJobId(),
            'uuid' => $job->uuid(),
            'name' => method_exists($job, 'resolveName') ? $job->resolveName() : $job::class,
            'attempts' => $job->attempts(),
        ];
    }

    /**
     * Execute the queued job name operation.
     *
     * @param  mixed  $job  The job value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private static function queuedJobName(mixed $job): string
    {
        if (is_object($job)) {
            return $job::class;
        }

        return (string) $job;
    }

    /**
     * Execute the http client context operation.
     *
     * @param  ClientRequest  $request  Incoming HTTP request.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private static function httpClientContext(ClientRequest $request): array
    {
        $url = $request->url();

        return [
            'method' => $request->method(),
            'scheme' => parse_url($url, PHP_URL_SCHEME),
            'host' => parse_url($url, PHP_URL_HOST),
            'path' => parse_url($url, PHP_URL_PATH),
        ];
    }

    /**
     * Execute the model event name operation.
     *
     * @param  string  $event  The event value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private static function modelEventName(string $event): string
    {
        return str($event)->before(':')->after('eloquent.')->toString();
    }

    /**
     * Execute the redact sql operation.
     *
     * @param  string  $sql  The sql value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private static function redactSql(string $sql): string
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", "'?'", $sql) ?? $sql;
        $sql = preg_replace('/"(?:\\\\"|[^"])*"/', '"?"', $sql) ?? $sql;

        return $sql;
    }
}
