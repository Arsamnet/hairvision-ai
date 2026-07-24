<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model for assigning AI model access records to clinics.
 *
 * @since 1.0.0
 */
class ClinicAiModelAccess extends Model
{
    use HasFactory;

    protected $table = 'clinic_ai_model_access';

    protected $fillable = [
        'clinic_id',
        'ai_model_access_id',
        'is_active',
        'assigned_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Clinic tenant receiving access.
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
     * AI model access record assigned to the clinic.
     *
     * @return BelongsTo<AiModelAccess, $this>
     *
     * @since 1.0.0
     */
    public function aiModelAccess(): BelongsTo
    {
        return $this->belongsTo(AiModelAccess::class);
    }

    /**
     * Admin user that made the assignment.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
