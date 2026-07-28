<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered clinic frequently asked question and answer.
 *
 * @since 2.0.1
 */
class GuideFaq extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'guide_page_id',
        'question',
        'answer_html',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = ['sort_order' => 'integer'];

    /**
     * Return the parent FAQ page.
     *
     * @return BelongsTo<GuidePage, $this>
     *
     * @since 2.0.1
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(GuidePage::class, 'guide_page_id');
    }
}
