<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execute the up operation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        Schema::create('ai_model_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('icon')->nullable();
            $table->string('card_image_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_model_category_outputs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ai_model_category_id')->constrained('ai_model_categories')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('prompt');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('credit_cost')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ai_model_category_id', 'code'], 'ai_category_outputs_category_code_unique');
            $table->index(['ai_model_category_id', 'is_active', 'sort_order'], 'ai_category_outputs_active_order_index');
        });

        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ai_model_category_id')->constrained('ai_model_categories')->restrictOnDelete();
            $table->string('type')->index();
            $table->string('name');
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->unsignedInteger('concurrency_limit')->default(1);
            $table->json('settings')->nullable();
            $table->text('credentials')->nullable();
            $table->string('health_status')->default('unknown')->index();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('legacy_ai_model_access_id')->nullable()->constrained('ai_model_accesses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ai_model_category_id', 'status', 'health_status'], 'ai_models_category_status_health_index');
            $table->index(['type', 'status']);
        });

        Schema::create('ai_model_output_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->foreignId('ai_model_category_output_id')->constrained('ai_model_category_outputs')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['ai_model_id', 'ai_model_category_output_id'], 'ai_model_output_configs_model_output_unique');
        });

        Schema::create('lead_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'lead_id']);
        });

        Schema::create('lead_source_images', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_profile_id')->constrained('lead_profiles')->cascadeOnDelete();
            $table->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('is_current')->default(true)->index();
            $table->timestamp('replaced_at')->nullable();
            $table->string('disk');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->string('checksum')->nullable();
            $table->string('status')->default('validated')->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'lead_id', 'version']);
            $table->index(['tenant_id', 'lead_id', 'is_current'], 'lead_source_images_current_index');
        });

        Schema::create('generation_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_source_image_id')->constrained('lead_source_images')->cascadeOnDelete();
            $table->foreignId('ai_model_category_id')->constrained('ai_model_categories')->restrictOnDelete();
            $table->foreignId('ai_model_category_output_id')->constrained('ai_model_category_outputs')->restrictOnDelete();
            $table->foreignId('current_generated_asset_id')->nullable()->constrained('generated_assets')->nullOnDelete();
            $table->foreignId('credit_transaction_id')->nullable()->constrained('credit_transactions')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('idempotency_key');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('safe_error_code')->nullable();
            $table->text('safe_error_message')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'lead_id', 'idempotency_key'], 'generation_requests_idempotency_unique');
            $table->index(['tenant_id', 'lead_id', 'status'], 'generation_requests_lead_status_index');
        });

        Schema::create('generation_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('generation_request_id')->constrained('generation_requests')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('ai_model_id')->constrained('ai_models')->restrictOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('queue_position')->nullable();
            $table->text('prompt');
            $table->string('provider_reference')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('provider_call_started_at')->nullable();
            $table->timestamp('provider_call_finished_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('safe_failure_class')->nullable();
            $table->text('safe_error_message')->nullable();
            $table->timestamps();

            $table->unique(['generation_request_id', 'attempt_number'], 'generation_attempts_request_number_unique');
            $table->index(['ai_model_id', 'status', 'created_at'], 'generation_attempts_model_status_index');
            $table->index(['tenant_id', 'lead_id', 'status'], 'generation_attempts_lead_status_index');
        });

        Schema::table('generated_assets', function (Blueprint $table): void {
            $table->foreignId('generation_group_id')->nullable()->change();
            $table->foreignId('generation_job_id')->nullable()->change();
            $table->foreignId('lead_source_image_id')->nullable()->after('lead_id')->constrained('lead_source_images')->nullOnDelete();
            $table->foreignId('ai_model_category_id')->nullable()->after('lead_source_image_id')->constrained('ai_model_categories')->nullOnDelete();
            $table->foreignId('ai_model_category_output_id')->nullable()->after('ai_model_category_id')->constrained('ai_model_category_outputs')->nullOnDelete();
            $table->foreignId('generation_request_id')->nullable()->after('ai_model_category_output_id')->constrained('generation_requests')->nullOnDelete();
            $table->foreignId('generation_attempt_id')->nullable()->after('generation_request_id')->constrained('generation_attempts')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('period');
            $table->boolean('is_current')->default(true)->after('version')->index();
            $table->string('checksum')->nullable()->after('size');
            $table->timestamp('archived_at')->nullable()->after('checksum');
        });

        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->string('reservation_status')->nullable()->after('type')->index();
            $table->foreignId('generation_request_id')->nullable()->after('related_id')->constrained('generation_requests')->nullOnDelete();
        });

        $this->seedInitialCategories();
    }

    /**
     * Execute the down operation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_request_id');
            $table->dropColumn('reservation_status');
        });

        Schema::table('generated_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('generation_attempt_id');
            $table->dropConstrainedForeignId('generation_request_id');
            $table->dropConstrainedForeignId('ai_model_category_output_id');
            $table->dropConstrainedForeignId('ai_model_category_id');
            $table->dropConstrainedForeignId('lead_source_image_id');
            $table->dropColumn(['version', 'is_current', 'checksum', 'archived_at']);
        });

        Schema::dropIfExists('generation_attempts');
        Schema::dropIfExists('generation_requests');
        Schema::dropIfExists('lead_source_images');
        Schema::dropIfExists('lead_profiles');
        Schema::dropIfExists('ai_model_output_configs');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_model_category_outputs');
        Schema::dropIfExists('ai_model_categories');
    }

    /**
     * Execute the seed initial categories operation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function seedInitialCategories(): void
    {
        $now = now();

        $hairId = DB::table('ai_model_categories')->insertGetId([
            'uuid' => (string) str()->uuid(),
            'code' => 'hair_transplant',
            'name' => 'کاشت مو',
            'description' => 'خروجی‌های شبیه‌سازی کاشت مو',
            'status' => 'active',
            'sort_order' => 10,
            'icon' => 'sparkles',
            'card_image_path' => '/theme/category-icons/hair-transplant.svg',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eyebrowId = DB::table('ai_model_categories')->insertGetId([
            'uuid' => (string) str()->uuid(),
            'code' => 'eyebrow_transplant',
            'name' => 'کاشت ابرو',
            'description' => 'خروجی‌های شبیه‌سازی کاشت ابرو',
            'status' => 'active',
            'sort_order' => 20,
            'icon' => 'eye',
            'card_image_path' => '/theme/category-icons/eyebrow-transplant.svg',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('ai_model_category_outputs')->insert([
            [
                'uuid' => (string) str()->uuid(),
                'ai_model_category_id' => $hairId,
                'code' => 'two_weeks',
                'name' => 'یک روز بعد از کاشت مو',
                'description' => 'نمای طبیعی روز اول با گرافت‌های تازه، قرمزی ملایم و ریزدلمه‌های قابل‌انتظار',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 day after a complete hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Reconstruct a natural, age-appropriate hairline and fully implant all necessary bald or thinning recipient areas, including the frontal hairline, temples, frontal scalp, mid-scalp, and top. Do not limit the transplant to existing hair. Show a realistic day-one result: closely shaved recipient areas, densely placed visible grafts, tiny red implantation dots, mild redness, minimal swelling, and fresh micro-scabbing. The graft pattern must clearly cover the previously bald areas, but must remain medically believable and not overly graphic. Do not show grown or dense hair at this stage. Match the person’s natural hair color and texture. Ultra-realistic, high-resolution, unedited documentary medical-photo style. The only difference from the source image must be the newly transplanted scalp and reconstructed hairline.
PROMPT,
                'sort_order' => 10,
                'credit_cost' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => (string) str()->uuid(),
                'ai_model_category_id' => $hairId,
                'code' => 'intermediate_regrowth',
                'name' => '۶ ماه بعد از کاشت مو',
                'description' => 'رشد میانی شش‌ماهه با خط رویش شکل‌گرفته، تراکم ناکامل و نمایان بودن طبیعی بخشی از پوست سر',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 6 months after a complete hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Show intermediate regrowth across all transplanted areas, including the reconstructed frontal hairline, temples, frontal scalp, mid-scalp, and top. New hair must extend into previously bald areas rather than only thickening the original hair. The wounds, redness, and scabs must be fully healed. The established hairline should be visible but still sparse, short, thin, slightly uneven, and incomplete. Keep some scalp visible and avoid final-stage density. Match the person’s natural hair color, direction, and texture. Ultra-realistic, high-resolution, unedited documentary style. The only difference from the source image must be the realistic 6-month hair-transplant growth.
PROMPT,
                'sort_order' => 20,
                'credit_cost' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => (string) str()->uuid(),
                'ai_model_category_id' => $hairId,
                'code' => 'final_result',
                'name' => 'یک سال بعد از کاشت مو',
                'description' => 'نتیجه کامل یک‌ساله با خط رویش طبیعی، پوشش متناسب و تراکم باورپذیر',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 year after a complete and successful hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Naturally restore all previously bald or thinning areas, including the frontal hairline, temples, frontal scalp, mid-scalp, and top. Do not merely thicken the remaining original hair. Create a natural, age-appropriate hairline proportional to the person’s face. Show fully grown, healthy, naturally fuller hair with realistic density, direction, color, and texture matching the original person. The transplanted areas should no longer appear bald, while retaining slight natural irregularity and subtle scalp visibility where appropriate. Avoid excessive density, sharp artificial hairlines, perfect symmetry, helmet hair, or a wig-like appearance. Ultra-realistic, high-resolution, unedited documentary style. The only difference from the source image must be the natural final hair-transplant result.
PROMPT,
                'sort_order' => 30,
                'credit_cost' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'uuid' => (string) str()->uuid(),
                'ai_model_category_id' => $eyebrowId,
                'code' => 'eyebrow_final_result',
                'name' => 'یک سال بعد از کاشت ابرو',
                'description' => 'نتیجه کامل یک‌ساله با تراکم، جهت رشد و عدم تقارن طبیعی ابروها',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 year after a successful eyebrow transplant. Modify ONLY the eyebrows. Preserve the exact identity, face, age, skin tone, eyes, expression, pose, camera angle, framing, clothing, head hair, facial hair, background, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Create naturally fuller, well-defined, age-appropriate eyebrows proportional to the face. Match the person’s natural eyebrow color and texture, with realistic individual hairs and growth direction: slightly upward at the inner brow, gradually angled outward through the arch and tail. Keep subtle irregularity and natural asymmetry. The transplant must appear fully healed, with no redness, scabs, swelling, or visible procedure marks. Avoid excessive thickness, sharp edges, perfect symmetry, painted-on makeup, tattooed or microbladed appearance, and artificial density. Ultra-realistic, high-resolution, unedited documentary-photo style. The only difference from the source image must be the realistic 1-year eyebrow-transplant result.
PROMPT,
                'sort_order' => 10,
                'credit_cost' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};
