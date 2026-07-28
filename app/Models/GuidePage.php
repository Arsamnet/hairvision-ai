<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * System-owned page metadata for clinic help content.
 *
 * @since 2.0.1
 */
class GuidePage extends Model
{
    use HasFactory, HasUuid;

    public const GUIDE = 'guide';

    public const FAQ = 'faq';

    protected $fillable = [
        'key',
        'title',
        'introduction_html',
        'created_by',
        'updated_by',
    ];

    /**
     * Return the ordered guide sections belonging to this page.
     *
     * @return HasMany<GuideSection, $this>
     *
     * @since 2.0.1
     */
    public function sections(): HasMany
    {
        return $this->hasMany(GuideSection::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Return the ordered frequently asked questions belonging to this page.
     *
     * @return HasMany<GuideFaq, $this>
     *
     * @since 2.0.1
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(GuideFaq::class)->orderBy('sort_order')->orderBy('id');
    }
}
