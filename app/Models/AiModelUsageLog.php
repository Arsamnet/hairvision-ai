<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Safe execution log for AI model usage.
 *
 * Logs intentionally avoid provider credentials and private full payloads.
 *
 * @since 1.0.0
 */
class AiModelUsageLog extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'clinic_id',
        'lead_id',
        'user_id',
        'ai_model_access_id',
        'usage_category_key',
        'status',
        'credit_consumed',
        'request_reference',
        'response_reference',
        'error_code',
        'error_message',
        'metadata_json',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'credit_consumed' => 'integer',
        'metadata_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Clinic tenant where the model was used.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 1.0.0
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'clinic_id');
    }

    /**
     * Verified lead whose image caused this model execution.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 2.0.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Dashboard user that initiated usage when available.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * AI model access record used for execution.
     *
     * @return BelongsTo<AiModelAccess, $this>
     *
     * @since 1.0.0
     */
    public function aiModelAccess(): BelongsTo
    {
        return $this->belongsTo(AiModelAccess::class);
    }
}
