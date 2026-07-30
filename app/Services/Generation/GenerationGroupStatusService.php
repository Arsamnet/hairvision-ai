<?php

namespace App\Services\Generation;

use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use Illuminate\Support\Facades\DB;

/**
 * Derives a legacy generation-group status without coupling sibling stages.
 *
 * @since 2.0.0
 */
class GenerationGroupStatusService
{
    /**
     * Recalculate group state from independently executing stage jobs.
     *
     * @param  GenerationGroup  $group  Group whose jobs have changed.
     * @return GenerationGroup Refreshed group state.
     *
     * @since 2.0.0
     */
    public function refresh(GenerationGroup $group): GenerationGroup
    {
        return DB::transaction(function () use ($group): GenerationGroup {
            $locked = GenerationGroup::query()->lockForUpdate()->findOrFail($group->id);
            $jobs = $locked->jobs()->lockForUpdate()->get();
            $allTerminal = $jobs->isNotEmpty()
                && $jobs->every(fn (GenerationJob $job): bool => in_array($job->status, GenerationJob::TERMINAL_STATUSES, true));
            $allCompleted = $jobs->isNotEmpty()
                && $jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed');

            if ($allCompleted) {
                $status = 'completed';
            } elseif ($allTerminal && $jobs->contains('status', 'failed')) {
                $status = 'failed';
            } elseif ($allTerminal) {
                $status = 'cancelled';
            } elseif ($jobs->contains(fn (GenerationJob $job): bool => in_array($job->status, ['processing', 'retrying'], true))) {
                $status = 'processing';
            } else {
                $status = 'queued';
            }

            $locked->forceFill([
                'status' => $status,
                'started_at' => $locked->started_at ?? ($status === 'processing' ? now() : null),
                'completed_at' => $allTerminal ? ($locked->completed_at ?? now()) : null,
            ])->save();

            return $locked->refresh();
        });
    }
}
