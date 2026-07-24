<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Assigns a UUID to models that expose a nullable `uuid` column.
 *
 * @since 1.0.0
 */
trait HasUuid
{
    /**
     * Register the creating hook that fills an empty UUID.
     *
     * Side effects: mutates the model before its first database insert.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }
}
