<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog entry for a supported SMS vendor.
 *
 * @since 1.2.0
 */
#[Fillable(['key', 'name', 'description', 'is_active'])]
class SmsProviderType extends Model
{
    /**
     * Provider accounts configured for this vendor type.
     *
     * @return HasMany<SmsProviderAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(SmsProviderAccount::class);
    }
}
