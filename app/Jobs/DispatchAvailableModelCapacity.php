<?php

namespace App\Jobs;

use App\Services\Generation\ModelQueueCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Wakes one model queue immediately after a provider-requested cooldown.
 *
 * @since 2.0.0
 */
class DispatchAvailableModelCapacity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Create a model-capacity wake-up job with an identifier-only payload.
     *
     * @param  int  $modelId  AI model database identifier.
     *
     * @since 2.0.0
     */
    public function __construct(public int $modelId)
    {
        $this->afterCommit();
    }

    /**
     * Dispatch attempts whose provider cooldown has elapsed.
     *
     * @param  ModelQueueCoordinator  $queue  Logical model queue coordinator.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function handle(ModelQueueCoordinator $queue): void
    {
        $queue->dispatchAvailableForModel($this->modelId);
    }
}
