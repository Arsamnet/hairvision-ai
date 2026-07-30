<?php

namespace App\Services\Generation;

use App\Models\ImageProviderLease;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Enforces one application-wide limit for concurrent image-provider calls.
 *
 * The cache lock serializes capacity decisions while expiring database rows
 * make capacity recoverable after a worker crash on database-only hosting.
 *
 * @since 2.0.0
 */
class ProviderConcurrencyLimiter
{
    /**
     * Attempt to reserve one provider slot for a generation record.
     *
     * @param  string  $ownerType  Non-sensitive owner discriminator.
     * @param  int  $ownerId  Database id of the generation job or attempt.
     * @param  string  $workerId  Safe worker identifier.
     * @return ImageProviderLease|null Acquired lease or null when capacity is full.
     *
     * @since 2.0.0
     */
    public function acquire(string $ownerType, int $ownerId, string $workerId): ?ImageProviderLease
    {
        return Cache::lock('hairvision:image-provider-semaphore', 10)->block(5, function () use ($ownerType, $ownerId, $workerId): ?ImageProviderLease {
            ImageProviderLease::query()->where('expires_at', '<=', now())->delete();

            $existing = ImageProviderLease::query()
                ->where('owner_type', $ownerType)
                ->where('owner_id', $ownerId)
                ->first();

            if ($existing) {
                return null;
            }

            if ($this->activeCount() >= $this->limit()) {
                return null;
            }

            return ImageProviderLease::query()->create([
                'lease_id' => (string) Str::uuid(),
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'worker_id' => $workerId,
                'acquired_at' => now(),
                'expires_at' => now()->addSeconds(
                    max(60, (int) config('hairvision.image_queue.worker_timeout', 1020) + 60),
                ),
            ]);
        });
    }

    /**
     * Release a previously acquired provider slot.
     *
     * @param  ImageProviderLease|null  $lease  Lease returned by acquire.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function release(?ImageProviderLease $lease): void
    {
        if (! $lease) {
            return;
        }

        ImageProviderLease::query()
            ->where('lease_id', $lease->lease_id)
            ->delete();
    }

    /**
     * Return the number of non-expired provider leases.
     *
     * @return int Active provider calls.
     *
     * @since 2.0.0
     */
    public function activeCount(): int
    {
        return ImageProviderLease::query()->where('expires_at', '>', now())->count();
    }

    /**
     * Return the configured provider concurrency ceiling.
     *
     * @return int Positive concurrency limit.
     *
     * @since 2.0.0
     */
    public function limit(): int
    {
        return max(1, (int) config('hairvision.image_queue.provider_max_concurrency', 1));
    }

    /**
     * Calculate a short jittered delay when provider capacity is full.
     *
     * @return int Delay in seconds.
     *
     * @since 2.0.0
     */
    public function retryDelaySeconds(): int
    {
        $minimum = max(1, (int) config('hairvision.image_queue.provider_capacity_delay_min_seconds', 3));
        $maximum = max($minimum, (int) config('hairvision.image_queue.provider_capacity_delay_max_seconds', 8));

        return random_int($minimum, $maximum);
    }
}
