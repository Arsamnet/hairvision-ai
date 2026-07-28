<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One actionable ordered step in a clinic guide section.
 *
 * @since 2.0.1
 */
class GuideStep extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'guide_section_id',
        'title',
        'body_html',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['sort_order' => 'integer'];

    /**
     * Return the parent guide section.
     *
     * @return BelongsTo<GuideSection, $this>
     *
     * @since 2.0.1
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(GuideSection::class, 'guide_section_id');
    }
}
