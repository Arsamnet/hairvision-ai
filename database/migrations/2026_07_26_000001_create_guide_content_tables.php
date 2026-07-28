<?php

use App\Support\AdminGuideDefaults;
use App\Support\Html\SimpleRichTextSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Moves clinic guide content from one JSON setting into independently editable records.
 *
 * @since 2.0.1
 */
return new class extends Migration
{
    private const LEGACY_KEY = 'guide.content';

    /**
     * Create guide-domain tables and preserve any existing managed content.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function up(): void
    {
        Schema::create('guide_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 40)->unique();
            $table->string('title', 150);
            $table->longText('introduction_html');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('guide_sections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guide_page_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('audience', 150)->nullable();
            $table->longText('description_html');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['guide_page_id', 'sort_order']);
        });

        Schema::create('guide_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guide_section_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->longText('body_html');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['guide_section_id', 'sort_order']);
        });

        Schema::create('guide_faqs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('guide_page_id')->constrained()->cascadeOnDelete();
            $table->string('question', 255);
            $table->longText('answer_html');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['guide_page_id', 'sort_order']);
        });

        $this->importContent($this->legacyContent() ?? AdminGuideDefaults::content());
        DB::table('system_settings')->where('key', self::LEGACY_KEY)->delete();
    }

    /**
     * Restore a portable legacy document before removing the guide tables.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function down(): void
    {
        $content = $this->exportContent();
        $now = now();

        DB::table('system_settings')->updateOrInsert(
            ['key' => self::LEGACY_KEY],
            [
                'uuid' => (string) Str::uuid(),
                'group' => 'guide',
                'label' => 'محتوای راهنمای کاربران کلینیک',
                'type' => 'json',
                'value_json' => json_encode(['value' => $content], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'encrypted_value' => null,
                'description' => 'بخش‌ها، گام‌ها و پرسش‌های متداول راهنمای کارکنان کلینیک.',
                'is_encrypted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        Schema::dropIfExists('guide_faqs');
        Schema::dropIfExists('guide_steps');
        Schema::dropIfExists('guide_sections');
        Schema::dropIfExists('guide_pages');
    }

    /**
     * Read a structurally valid legacy guide document.
     *
     * @return array<string, mixed>|null Legacy content when available.
     *
     * @since 2.0.1
     */
    private function legacyContent(): ?array
    {
        $raw = DB::table('system_settings')->where('key', self::LEGACY_KEY)->value('value_json');
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $content = is_array($decoded) ? ($decoded['value'] ?? null) : null;

        return is_array($content)
            && is_string($content['title'] ?? null)
            && is_array($content['sections'] ?? null)
            && is_array($content['faqs'] ?? null)
                ? $content
                : null;
    }

    /**
     * Insert the guide and FAQ records in one transaction.
     *
     * @param  array<string, mixed>  $content  Legacy or default structured content.
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    private function importContent(array $content): void
    {
        DB::transaction(function () use ($content): void {
            $now = now();
            $guidePageId = DB::table('guide_pages')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'key' => 'guide',
                'title' => trim((string) ($content['title'] ?? 'راهنمای کاربران کلینیک HairVision')),
                'introduction_html' => $this->richText((string) ($content['introduction'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $faqPageId = DB::table('guide_pages')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'key' => 'faq',
                'title' => (string) __('guide.faq_page_title'),
                'introduction_html' => $this->richText((string) __('guide.faq_page_description')),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_values($content['sections'] ?? []) as $sectionIndex => $section) {
                if (! is_array($section)) {
                    continue;
                }

                $sectionId = DB::table('guide_sections')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'guide_page_id' => $guidePageId,
                    'title' => trim((string) ($section['title'] ?? '')),
                    'audience' => filled($section['audience'] ?? null) ? trim((string) $section['audience']) : null,
                    'description_html' => $this->richText((string) ($section['description'] ?? '')),
                    'sort_order' => ($sectionIndex + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (array_values($section['steps'] ?? []) as $stepIndex => $step) {
                    if (! is_array($step)) {
                        continue;
                    }

                    DB::table('guide_steps')->insert([
                        'uuid' => (string) Str::uuid(),
                        'guide_section_id' => $sectionId,
                        'title' => trim((string) ($step['title'] ?? '')),
                        'body_html' => $this->richText((string) ($step['body'] ?? '')),
                        'sort_order' => ($stepIndex + 1) * 10,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach (array_values($content['faqs'] ?? []) as $faqIndex => $faq) {
                if (! is_array($faq)) {
                    continue;
                }

                DB::table('guide_faqs')->insert([
                    'uuid' => (string) Str::uuid(),
                    'guide_page_id' => $faqPageId,
                    'question' => trim((string) ($faq['question'] ?? '')),
                    'answer_html' => $this->richText((string) ($faq['answer'] ?? '')),
                    'sort_order' => ($faqIndex + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * Export relational content for a safe migration rollback.
     *
     * @return array<string, mixed> Structured legacy guide document.
     *
     * @since 2.0.1
     */
    private function exportContent(): array
    {
        $guide = DB::table('guide_pages')->where('key', 'guide')->first();
        $faq = DB::table('guide_pages')->where('key', 'faq')->first();

        if (! $guide || ! $faq) {
            return AdminGuideDefaults::content();
        }

        $sections = DB::table('guide_sections')->where('guide_page_id', $guide->id)->orderBy('sort_order')->get();

        return [
            'title' => $guide->title,
            'introduction' => $guide->introduction_html,
            'sections' => $sections->map(fn (object $section): array => [
                'title' => $section->title,
                'audience' => $section->audience ?? '',
                'description' => $section->description_html,
                'steps' => DB::table('guide_steps')->where('guide_section_id', $section->id)->orderBy('sort_order')->get()
                    ->map(fn (object $step): array => ['title' => $step->title, 'body' => $step->body_html])->all(),
            ])->all(),
            'faqs' => DB::table('guide_faqs')->where('guide_page_id', $faq->id)->orderBy('sort_order')->get()
                ->map(fn (object $item): array => ['question' => $item->question, 'answer' => $item->answer_html])->all(),
        ];
    }

    /**
     * Convert plain text or existing HTML into allowlisted rich text.
     *
     * @param  string  $value  Imported content.
     * @return string Sanitized HTML.
     *
     * @since 2.0.1
     */
    private function richText(string $value): string
    {
        $value = trim($value);
        $sanitizer = new SimpleRichTextSanitizer;

        if ($value === '') {
            return '';
        }

        if (preg_match('/<\s*\/?\s*[a-z][^>]*>/i', $value) === 1) {
            return $sanitizer->sanitize($value);
        }

        return $sanitizer->sanitize('<p>'.nl2br(e($value), false).'</p>');
    }
};
