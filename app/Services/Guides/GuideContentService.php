<?php

namespace App\Services\Guides;

use App\Models\GuideFaq;
use App\Models\GuidePage;
use App\Models\GuideSection;
use App\Models\GuideStep;
use App\Support\Html\SimpleRichTextSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Provides transactional domain operations for system-owned clinic help content.
 *
 * @since 2.0.1
 */
final class GuideContentService
{
    /**
     * Create the guide content service.
     *
     * @param  SimpleRichTextSanitizer  $sanitizer  Shared rich-text allowlist sanitizer.
     *
     * @since 2.0.1
     */
    public function __construct(private readonly SimpleRichTextSanitizer $sanitizer) {}

    /**
     * Return the guide page with its ordered content.
     *
     * @return GuidePage Loaded guide page.
     *
     * @since 2.0.1
     */
    public function guidePage(): GuidePage
    {
        return GuidePage::query()
            ->where('key', GuidePage::GUIDE)
            ->with('sections.steps')
            ->firstOrFail();
    }

    /**
     * Return the FAQ page with its ordered questions.
     *
     * @return GuidePage Loaded FAQ page.
     *
     * @since 2.0.1
     */
    public function faqPage(): GuidePage
    {
        return GuidePage::query()
            ->where('key', GuidePage::FAQ)
            ->with('faqs')
            ->firstOrFail();
    }

    /**
     * Return render-ready clinic guide content.
     *
     * @return array<string, mixed> Structured guide content.
     *
     * @since 2.0.1
     */
    public function guide(): array
    {
        $page = $this->guidePage();

        return [
            'title' => $page->title,
            'introduction' => $this->richText($page->introduction_html),
            'sections' => $page->sections->map(fn (GuideSection $section): array => [
                'id' => $section->id,
                'title' => $section->title,
                'audience' => $section->audience ?? '',
                'description' => $this->richText($section->description_html),
                'steps' => $section->steps->map(fn (GuideStep $step): array => [
                    'id' => $step->id,
                    'title' => $step->title,
                    'body' => $this->richText($step->body_html),
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Return render-ready clinic FAQ content.
     *
     * @return array<string, mixed> Structured FAQ content.
     *
     * @since 2.0.1
     */
    public function faq(): array
    {
        $page = $this->faqPage();

        return [
            'title' => $page->title,
            'introduction' => $this->richText($page->introduction_html),
            'faqs' => $page->faqs->map(fn (GuideFaq $faq): array => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $this->richText($faq->answer_html),
            ])->all(),
        ];
    }

    /**
     * Convert plain text or submitted HTML into allowlisted rich text.
     *
     * @param  string|null  $value  Plain text or HTML.
     * @return string Sanitized HTML.
     *
     * @since 2.0.1
     */
    public function richText(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $value) === 1) {
            return $this->sanitizer->sanitize($value);
        }

        $paragraphs = preg_split('/\R{2,}/u', $value) ?: [$value];
        $html = collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');

        return $this->sanitizer->sanitize($html);
    }

    /**
     * Update page metadata and record the responsible administrator.
     *
     * @param  GuidePage  $page  Page being updated.
     * @param  array{title: string, introduction: string}  $data  Validated page data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuidePage Updated page.
     *
     * @since 2.0.1
     */
    public function updatePage(GuidePage $page, array $data, ?int $actorId): GuidePage
    {
        $page->update([
            'title' => trim($data['title']),
            'introduction_html' => $this->richText($data['introduction']),
            'updated_by' => $actorId,
        ]);

        return $page->refresh();
    }

    /**
     * Add a section to the end of the clinic guide.
     *
     * @param  GuidePage  $page  Parent guide page.
     * @param  array{title: string, audience?: string|null, description: string}  $data  Validated section data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuideSection Created section.
     *
     * @since 2.0.1
     */
    public function createSection(GuidePage $page, array $data, ?int $actorId): GuideSection
    {
        return $page->sections()->create([
            ...$this->sectionAttributes($data),
            'sort_order' => $this->nextOrder(GuideSection::class, 'guide_page_id', $page->id),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * Update one guide section without rewriting sibling content.
     *
     * @param  GuideSection  $section  Section being updated.
     * @param  array{title: string, audience?: string|null, description: string}  $data  Validated section data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuideSection Updated section.
     *
     * @since 2.0.1
     */
    public function updateSection(GuideSection $section, array $data, ?int $actorId): GuideSection
    {
        $section->update([...$this->sectionAttributes($data), 'updated_by' => $actorId]);

        return $section->refresh();
    }

    /**
     * Add a step to the end of a guide section.
     *
     * @param  GuideSection  $section  Parent section.
     * @param  array{title: string, body: string}  $data  Validated step data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuideStep Created step.
     *
     * @since 2.0.1
     */
    public function createStep(GuideSection $section, array $data, ?int $actorId): GuideStep
    {
        return $section->steps()->create([
            ...$this->stepAttributes($data),
            'sort_order' => $this->nextOrder(GuideStep::class, 'guide_section_id', $section->id),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * Update one guide step.
     *
     * @param  GuideStep  $step  Step being updated.
     * @param  array{title: string, body: string}  $data  Validated step data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuideStep Updated step.
     *
     * @since 2.0.1
     */
    public function updateStep(GuideStep $step, array $data, ?int $actorId): GuideStep
    {
        $step->update([...$this->stepAttributes($data), 'updated_by' => $actorId]);

        return $step->refresh();
    }

    /**
     * Add a question to the end of the FAQ page.
     *
     * @param  GuidePage  $page  Parent FAQ page.
     * @param  array{question: string, answer: string}  $data  Validated FAQ data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuideFaq Created FAQ.
     *
     * @since 2.0.1
     */
    public function createFaq(GuidePage $page, array $data, ?int $actorId): GuideFaq
    {
        return $page->faqs()->create([
            ...$this->faqAttributes($data),
            'sort_order' => $this->nextOrder(GuideFaq::class, 'guide_page_id', $page->id),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * Update one frequently asked question.
     *
     * @param  GuideFaq  $faq  FAQ being updated.
     * @param  array{question: string, answer: string}  $data  Validated FAQ data.
     * @param  int|null  $actorId  Acting user identifier.
     * @return GuideFaq Updated FAQ.
     *
     * @since 2.0.1
     */
    public function updateFaq(GuideFaq $faq, array $data, ?int $actorId): GuideFaq
    {
        $faq->update([...$this->faqAttributes($data), 'updated_by' => $actorId]);

        return $faq->refresh();
    }

    /**
     * Move a section one position in the requested direction.
     *
     * @param  GuideSection  $section  Section being moved.
     * @param  string  $direction  Either up or down.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function moveSection(GuideSection $section, string $direction): void
    {
        $this->move($section, 'guide_page_id', $section->guide_page_id, $direction);
    }

    /**
     * Move a step one position in the requested direction.
     *
     * @param  GuideStep  $step  Step being moved.
     * @param  string  $direction  Either up or down.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function moveStep(GuideStep $step, string $direction): void
    {
        $this->move($step, 'guide_section_id', $step->guide_section_id, $direction);
    }

    /**
     * Move a FAQ one position in the requested direction.
     *
     * @param  GuideFaq  $faq  FAQ being moved.
     * @param  string  $direction  Either up or down.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function moveFaq(GuideFaq $faq, string $direction): void
    {
        $this->move($faq, 'guide_page_id', $faq->guide_page_id, $direction);
    }

    /**
     * Build sanitized section attributes.
     *
     * @param  array<string, mixed>  $data  Validated section data.
     * @return array<string, mixed> Persistence attributes.
     *
     * @since 2.0.1
     */
    private function sectionAttributes(array $data): array
    {
        return [
            'title' => trim((string) $data['title']),
            'audience' => filled($data['audience'] ?? null) ? trim((string) $data['audience']) : null,
            'description_html' => $this->richText((string) $data['description']),
        ];
    }

    /**
     * Build sanitized step attributes.
     *
     * @param  array<string, mixed>  $data  Validated step data.
     * @return array<string, mixed> Persistence attributes.
     *
     * @since 2.0.1
     */
    private function stepAttributes(array $data): array
    {
        return ['title' => trim((string) $data['title']), 'body_html' => $this->richText((string) $data['body'])];
    }

    /**
     * Build sanitized FAQ attributes.
     *
     * @param  array<string, mixed>  $data  Validated FAQ data.
     * @return array<string, mixed> Persistence attributes.
     *
     * @since 2.0.1
     */
    private function faqAttributes(array $data): array
    {
        return ['question' => trim((string) $data['question']), 'answer_html' => $this->richText((string) $data['answer'])];
    }

    /**
     * Determine a stable sort value after the current final sibling.
     *
     * @param  class-string<Model>  $modelClass  Sibling model class.
     * @param  string  $foreignKey  Parent key column.
     * @param  int  $parentId  Parent identifier.
     * @return int Next sort value.
     *
     * @since 2.0.1
     */
    private function nextOrder(string $modelClass, string $foreignKey, int $parentId): int
    {
        return ((int) $modelClass::query()->where($foreignKey, $parentId)->max('sort_order')) + 10;
    }

    /**
     * Re-sequence siblings transactionally after an adjacent swap.
     *
     * @param  Model  $model  Record being moved.
     * @param  string  $foreignKey  Parent key column.
     * @param  int  $parentId  Parent identifier.
     * @param  string  $direction  Either up or down.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    private function move(Model $model, string $foreignKey, int $parentId, string $direction): void
    {
        DB::transaction(function () use ($model, $foreignKey, $parentId, $direction): void {
            $siblings = $model->newQuery()
                ->where($foreignKey, $parentId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            $index = array_search($model->getKey(), $siblings, true);
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! array_key_exists($target, $siblings)) {
                return;
            }

            [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];

            foreach ($siblings as $position => $id) {
                $model->newQuery()->whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });
    }
}
