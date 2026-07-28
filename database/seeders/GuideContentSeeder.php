<?php

namespace Database\Seeders;

use App\Models\GuidePage;
use App\Services\Guides\GuideContentService;
use App\Support\AdminGuideDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds clinic help content without overwriting later administrator edits.
 *
 * @since 2.0.1
 */
class GuideContentSeeder extends Seeder
{
    /**
     * Create the guide and FAQ pages only when they do not already exist.
     *
     * @param  GuideContentService  $content  Guide domain service.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function run(GuideContentService $content): void
    {
        DB::transaction(function () use ($content): void {
            $defaults = AdminGuideDefaults::content();

            if (! GuidePage::query()->where('key', GuidePage::GUIDE)->exists()) {
                $page = GuidePage::query()->create([
                    'key' => GuidePage::GUIDE,
                    'title' => $defaults['title'],
                    'introduction_html' => $content->richText($defaults['introduction']),
                ]);

                foreach ($defaults['sections'] as $sectionData) {
                    $section = $content->createSection($page, $sectionData, null);

                    foreach ($sectionData['steps'] as $stepData) {
                        $content->createStep($section, $stepData, null);
                    }
                }
            }

            if (! GuidePage::query()->where('key', GuidePage::FAQ)->exists()) {
                $page = GuidePage::query()->create([
                    'key' => GuidePage::FAQ,
                    'title' => (string) __('guide.faq_page_title'),
                    'introduction_html' => $content->richText((string) __('guide.faq_page_description')),
                ]);

                foreach ($defaults['faqs'] as $faqData) {
                    $content->createFaq($page, $faqData, null);
                }
            }
        });
    }
}
