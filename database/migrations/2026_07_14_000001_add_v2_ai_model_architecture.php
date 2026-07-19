<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
                'name' => 'دو هفته بعد از کاشت',
                'description' => 'آثار ترمیم اولیه و خط رویش تازه',
                'prompt' => 'یک تصویر واقع‌گرایانه از همان مراجعه‌کننده در مرحله دو هفته بعد از کاشت مو بساز. هویت چهره، زاویه دوربین، نور، رنگ پوست و پس‌زمینه را حفظ کن و فقط ناحیه خط رویش و پوست سر را با نشانه‌های طبیعی ترمیم اولیه، قرمزی ملایم و فولیکول‌های تازه ویرایش کن.',
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
                'name' => 'مرحله رشد میانی',
                'description' => 'رشد تدریجی و تراکم در حال شکل‌گیری',
                'prompt' => 'یک تصویر واقع‌گرایانه از همان مراجعه‌کننده در مرحله رشد میانی بعد از کاشت مو بساز. هویت چهره و شرایط تصویر را حفظ کن و فقط ناحیه مو و خط رویش را با رشد تدریجی، تراکم در حال شکل‌گیری و ظاهر طبیعی و قابل قبول پزشکی ویرایش کن.',
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
                'name' => 'نتیجه نهایی',
                'description' => 'پوشش کامل‌تر و ظاهر طبیعی‌تر',
                'prompt' => 'یک تصویر واقع‌گرایانه از نتیجه نهایی کاشت مو برای همان مراجعه‌کننده بساز. هویت چهره، زاویه دوربین، نور، رنگ پوست و پس‌زمینه را حفظ کن و خط رویش، تراکم، جهت و بافت مو را طبیعی، متناسب با چهره و قابل قبول پزشکی نمایش بده.',
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
                'name' => 'نتیجه کاشت ابرو',
                'description' => 'فرم طبیعی‌تر و تراکم متناسب ابرو',
                'prompt' => 'یک تصویر واقع‌گرایانه از نتیجه کاشت ابرو برای همان مراجعه‌کننده بساز. هویت چهره، زاویه دوربین، نور، رنگ پوست، چشم‌ها و پس‌زمینه را حفظ کن و فقط ناحیه ابرو را با تراکم طبیعی، جهت خواب مناسب مو و فرم هماهنگ با چهره و قابل قبول پزشکی ویرایش کن.',
                'sort_order' => 10,
                'credit_cost' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
};
