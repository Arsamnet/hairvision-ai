<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordered topic grouping within the clinic user guide.
 *
 * @since 2.0.1
 */
class GuideSection extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'guide_page_id',
        'title',
        'audience',
        'description_html',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['sort_order' => 'integer'];

    /**
     * Return the parent guide page.
     *
     * @return BelongsTo<GuidePage, $this>
     *
     * @since 2.0.1
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(GuidePage::class, 'guide_page_id');
    }

    /**
     * Return the ordered steps within this section.
     *
     * @return HasMany<GuideStep, $this>
     *
     * @since 2.0.1
     */
    public function steps(): HasMany
    {
        return $this->hasMany(GuideStep::class)->orderBy('sort_order')->orderBy('id');
    }
}
