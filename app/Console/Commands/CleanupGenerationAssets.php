<?php

namespace App\Console\Commands;

use App\Models\GeneratedAsset;
use App\Models\ImageProviderLease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Applies conservative retention to archived, inactive generated assets.
 *
 * @since 2.0.0
 */
class CleanupGenerationAssets extends Command
{
    protected $signature = 'hairvision:generations:cleanup {--dry-run : Report without deleting files}';

    protected $description = 'Delete only expired archived generation files that have no active work.';

    /**
     * Purge eligible archived files and expired semaphore leases.
     *
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(): int
    {
        $hours = max(1, (int) config('hairvision.image_queue.result_retention_hours', 720));
        $activeMinutes = max(1, (int) config('hairvision.image_queue.active_session_retention_minutes', 120));
        $cutoff = now()->subHours($hours)->min(now()->subMinutes($activeMinutes));
        $dryRun = (bool) $this->option('dry-run');
        $deleted = 0;

        GeneratedAsset::query()
            ->where('is_current', false)
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $cutoff)
            ->whereDoesntHave('generationAttempt', fn ($query) => $query->whereIn('status', ['queued', 'reserved', 'dispatched', 'processing']))
            ->chunkById(100, function ($assets) use ($dryRun, &$deleted): void {
                foreach ($assets as $asset) {
                    if (! $dryRun) {
                        Storage::disk($asset->disk)->delete($asset->path);
                        $asset->delete();
                    }

                    $deleted++;
                }
            });

        if (! $dryRun) {
            ImageProviderLease::query()->where('expires_at', '<=', now())->delete();
        }

        $this->components->info(($dryRun ? 'eligible=' : 'deleted=').$deleted.' cutoff='.$cutoff->toISOString());

        return self::SUCCESS;
    }
}
