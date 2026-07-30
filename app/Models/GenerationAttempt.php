<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution attempt for a generation request on a selected model.
 *
 * @since 2.0.0
 */
class GenerationAttempt extends Model
{
    use HasFactory, HasUuid;

    public const NON_TERMINAL_STATUSES = ['queued', 'reserved', 'dispatched', 'processing'];

    protected $fillable = [
        'generation_request_id',
        'tenant_id',
        'lead_id',
        'ai_model_id',
        'credit_transaction_id',
        'attempt_number',
        'status',
        'queue_position',
        'available_at',
        'prompt',
        'provider_reference',
        'reserved_at',
        'dispatched_at',
        'queue_wait_ms',
        'provider_call_started_at',
        'provider_call_finished_at',
        'provider_latency_ms',
        'worker_id',
        'completed_at',
        'failed_at',
        'safe_failure_class',
        'safe_error_message',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'queue_position' => 'integer',
        'available_at' => 'datetime',
        'reserved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'queue_wait_ms' => 'integer',
        'provider_call_started_at' => 'datetime',
        'provider_call_finished_at' => 'datetime',
        'provider_latency_ms' => 'integer',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Define the generation request Eloquent relationship.
     *
     * @return BelongsTo<GenerationRequest, $this>
     *
     * @since 2.0.0
     */
    public function generationRequest(): BelongsTo
    {
        return $this->belongsTo(GenerationRequest::class);
    }

    /**
     * Define the model Eloquent relationship.
     *
     * @return BelongsTo<AiModel, $this>
     *
     * @since 2.0.0
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    /**
     * Credit reservation held for this provider attempt.
     *
     * @return BelongsTo<CreditTransaction, $this>
     *
     * @since 2.0.0
     */
    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }
}
