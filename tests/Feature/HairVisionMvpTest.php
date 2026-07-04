<?php

namespace Tests\Feature;

use App\Jobs\ProcessGenerationJob;
use App\Models\AiModelAccess;
use App\Models\AiProvider;
use App\Models\CreditTransaction;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Plan;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\Upload;
use App\Models\User;
use App\Services\Sms\SmsGatewayService;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HairVisionMvpTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_JPEG_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EFBQRAQAAAAAAAAAAAAAAAAAAARD/2gAIAQMBAT8QH//EFBQRAQAAAAAAAAAAAAAAAAAAARD/2gAIAQIBAT8QH//EFBABAQAAAAAAAAAAAAAAAAAAARD/2gAIAQEAAT8QH//Z';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');

        config([
            'app.debug' => true,
            'hairvision.debug_fast_samples' => true,
            'hairvision.default_ai_provider' => 'sample_image',
            'hairvision.otp.max_requests' => 2,
            'hairvision.otp.decay_seconds' => 600,
            'hairvision.otp.max_attempts' => 5,
        ]);
    }

    public function test_tenant_is_resolved_by_host_and_unknown_hosts_return_404(): void
    {
        $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        $this->forHost('aria.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'aria')
            ->assertJsonPath('features.ai_provider', 'sample_image');

        $this->forHost('unknown.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertNotFound();
    }

    public function test_landing_page_visit_is_tracked_before_any_lead_is_created(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk();

        $this->assertSame(1, $tenant->landingPageVisits()->count());
        $this->assertDatabaseHas('tenant_landing_page_visits', [
            'tenant_id' => $tenant->id,
            'host' => 'aria.hairvision-ai.test',
        ]);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_landing_page_uses_tenant_favicon_or_default_fallback(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('/build/assets/favicon-', false)
            ->assertDontSee('/build/img/favicon.png', false);

        $template = Template::create([
            'code' => 'clinic-favicon',
            'name' => 'Clinic Favicon',
            'is_active' => true,
        ]);

        TenantThemeSetting::create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'favicon_path' => '/favicon.ico',
        ]);

        $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('href="/favicon.ico"', false)
            ->assertDontSee('/build/img/favicon.png', false);

        $tenant->theme()->update(['favicon_path' => '/theme/missing-favicon.png']);

        $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('/build/assets/favicon-', false)
            ->assertDontSee('/theme/missing-favicon.png', false)
            ->assertDontSee('/build/img/favicon.png', false);

        $tenant->theme()->update(['favicon_path' => 'https://example.test/favicon.png']);

        $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('/build/assets/favicon-', false)
            ->assertDontSee('https://example.test/favicon.png', false)
            ->assertDontSee('/build/img/favicon.png', false);
    }

    public function test_otp_request_stores_only_hash_and_is_rate_limited(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->enableIppanelOtpGateway();

        $first = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertArrayNotHasKey('debug_sms', $first);

        $code = $this->latestSentOtpCode();

        $otp = $tenant->otpCodes()->firstOrFail();
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertOk();

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertStatus(429);
    }

    public function test_otp_request_sends_system_ippanel_pattern_when_configured(): void
    {
        $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        SmsGateway::create([
            'name' => 'Local System',
            'provider_code' => 'local',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => [],
            'settings_json' => ['purpose' => 'system_messages'],
        ]);

        SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => false,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);

        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        $payload = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09124248164'])
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('debug_sms', $payload);

        $code = $this->latestSentOtpCode();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($code): bool {
            return $request->url() === 'https://edge.ippanel.com/v1/api/send'
                && $request->header('Authorization') === ['system-token']
                && $request['sending_type'] === 'pattern'
                && $request['from_number'] === '+983000505'
                && $request['code'] === 's5jh64gwzlk0jf3'
                && $request['recipients'] === ['989124248164']
                && $request['params']['verification-code'] === $code;
        });
    }

    public function test_otp_request_exposes_debug_sms_for_local_system_provider(): void
    {
        $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        config(['hairvision.sms.default_provider' => 'local']);

        $payload = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09124248164'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('debug_sms', fn ($value) => is_string($value) && preg_match('/^[0-9]{6}$/', $value))
            ->json();

        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $payload['debug_sms']);
    }

    public function test_otp_verify_creates_verified_lead_for_current_tenant(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        $code = $this->requestOtpAndReturnCode('aria.hairvision-ai.test', '09123456789');

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '09123456789',
                'code' => $code,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lead.phone', '09123456789');

        $lead = Lead::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNotNull($lead->phone_verified_at);
        $this->assertSame('verified', $lead->status);
    }

    public function test_otp_accepts_supported_digit_scripts_and_rejects_other_characters(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->enableIppanelOtpGateway();

        $payload = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '۰۹۱۲۳۴۵۶۷۸۹'])
            ->assertOk()
            ->assertJsonPath('message', 'کد تایید ارسال شد.')
            ->json();

        $this->assertArrayNotHasKey('debug_sms', $payload);

        $code = $this->latestSentOtpCode();

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
                'code' => NumberNormalizer::toPersianDigits($code),
            ])
            ->assertOk()
            ->assertJsonPath('lead.phone', '09123456789');

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $tenant->id,
            'phone' => '09123456789',
            'phone_normalized' => '09123456789',
        ]);

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '0912abc6789'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'شماره موبایل باید فقط شامل ۱۱ رقم باشد.');

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
                'code' => '۱۲۳x۵۶',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'کد تایید باید فقط شامل ۶ رقم باشد.');
    }

    public function test_upload_requires_verified_lead_session_and_rejects_invalid_files(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        $this->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertForbidden();

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09123456789',
            'phone_normalized' => '09123456789',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
                'consent' => '1',
            ])
            ->assertJsonValidationErrors('image');
    }

    public function test_generation_requires_verified_lead_session_with_json_message(): void
    {
        $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', [
                'upload_id' => '11111111-1111-4111-8111-111111111111',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', __('auth.verify_phone_to_view_results'));
    }

    public function test_public_lead_clinic_admin_and_platform_admin_use_separate_session_cookies(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $clinicAdmin = User::factory()->create([
            'phone_normalized' => '09120000000',
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);
        $platformAdmin = User::factory()->create([
            'phone_normalized' => '09129999999',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $tenant->users()->attach($clinicAdmin->id, ['role' => 'clinic_admin', 'status' => 'active']);

        $code = $this->requestOtpAndReturnCode('aria.hairvision-ai.test', '09123456789');

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '09123456789',
                'code' => $code,
            ])
            ->assertOk()
            ->assertCookie(config('hairvision.sessions.lead_cookie'))
            ->assertCookieMissing(config('hairvision.sessions.clinic_admin_cookie'))
            ->assertCookieMissing(config('hairvision.sessions.platform_admin_cookie'));

        $this->forHost('aria.hairvision-ai.test')
            ->post('/admin/otp/request', ['phone' => '09120000000'])
            ->assertRedirect(route('admin.otp.form'))
            ->assertCookie(config('hairvision.sessions.clinic_admin_cookie'))
            ->assertCookieMissing(config('hairvision.sessions.lead_cookie'))
            ->assertCookieMissing(config('hairvision.sessions.platform_admin_cookie'));

        $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/request', ['phone' => $platformAdmin->phone_normalized])
            ->assertRedirect(route('admin.otp.form'))
            ->assertCookie(config('hairvision.sessions.platform_admin_cookie'))
            ->assertCookieMissing(config('hairvision.sessions.lead_cookie'))
            ->assertCookieMissing(config('hairvision.sessions.clinic_admin_cookie'));
    }

    public function test_sample_generation_flow_creates_three_private_assets_without_external_http(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        $this->seedSampleImages();
        Http::fake();

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09123456789',
            'phone_normalized' => '09123456789',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $smsGateway = SmsGateway::create([
            'name' => 'Local System',
            'provider_code' => 'local',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => [],
            'settings_json' => ['purpose' => 'system_messages'],
        ]);

        $this->mock(SmsGatewayService::class, function ($mock) use ($lead, $smsGateway): void {
            $mock->shouldReceive('sendSystemMessage')
                ->once()
                ->with($lead->phone_normalized, __('ai.generation.sms.completed'))
                ->andReturn($smsGateway);
        });

        $uploadId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('upload.status', 'validated')
            ->json('upload.id');

        $groupId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'completed')
            ->assertJsonPath('stages.0.key', 'two_weeks')
            ->assertJsonPath('jobs.0.period', 'two_weeks')
            ->assertJsonCount(3, 'jobs')
            ->json('generation_group.id');

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();
        $this->assertSame($tenant->id, $group->tenant_id);
        $this->assertSame('completed', $group->refresh()->status);
        $this->assertSame(['two_weeks', 'intermediate_regrowth', 'final_result'], $group->jobs()->orderBy('id')->pluck('period')->all());
        $this->assertSame(3, $group->jobs()->where('status', 'completed')->count());
        $this->assertSame(3, GeneratedAsset::query()->where('generation_group_id', $group->id)->count());
        $this->assertSame(3, CreditTransaction::query()->where('tenant_id', $tenant->id)->where('type', 'use')->count());
        $this->assertSame(7, SubscriptionServiceEntitlement::query()->where('tenant_id', $tenant->id)->where('service_key', 'hair_transplant')->firstOrFail()->remainingCredit());

        $this->assertDatabaseHas('uploads', [
            'uuid' => $uploadId,
            'tenant_id' => $tenant->id,
            'original_disk' => 'local',
            'status' => 'validated',
        ]);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->getJson("/api/v1/generations/{$groupId}/status")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('stages.2.key', 'final_result')
            ->assertJsonCount(3, 'jobs');

        $results = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->getJson("/api/v1/generations/{$groupId}/results")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('results.0.period', 'two_weeks')
            ->assertJsonCount(3, 'results')
            ->json('results');

        $asset = GeneratedAsset::query()->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($asset->path));
        $this->assertStringStartsWith('tenants/'.$tenant->uuid.'/leads/', $asset->path);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->get($results[0]['url'])
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        Http::assertNothingSent();

        $repeatGroupId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->json('generation_group.id');

        $this->assertSame($groupId, $repeatGroupId);
        $this->assertSame(1, GenerationGroup::query()->where('upload_id', $group->upload_id)->count());
    }

    public function test_gapgpt_generation_sends_three_independent_image_edit_requests(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);

        AiProvider::query()->firstOrCreate(
            ['code' => 'gapgpt'],
            [
                'name' => 'GapGPT',
                'type' => 'external',
                'base_url' => 'https://api.gapgpt.app/v1/images/edits',
                'auth_type' => 'bearer',
                'is_active' => true,
                'supports_async' => false,
            ]
        );

        $access = AiModelAccess::create([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT Test Access',
            'credentials' => ['api_key' => 'Bearer gap-secret'],
            'settings' => [
                'endpoint_url' => 'https://api.gapgpt.app/v1/images/edits',
                'model' => 'gpt-image-2',
                'size' => '1024x1024',
                'response_format' => 'url',
            ],
            'prompt' => '{stage_prompt}',
            'credit_limit' => 3,
            'is_active' => true,
            'priority' => 0,
        ]);

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $access->id => ['is_active' => true],
        ]);

        Http::fake([
            'https://api.gapgpt.app/v1/images/edits' => Http::response([
                'id' => 'gap-response',
                'data' => [
                    ['url' => 'https://cdn.gapgpt.test/generated/result.jpg'],
                ],
            ], 200, ['x-request-id' => 'gap-request']),
            'https://cdn.gapgpt.test/generated/result.jpg' => Http::response(base64_decode(self::TEST_JPEG_BASE64), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124441111',
            'phone_normalized' => '09124441111',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $uploadId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertOk()
            ->json('upload.id');

        $groupId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'completed')
            ->json('generation_group.id');

        $gapGptRequests = Http::recorded()
            ->filter(fn (array $record): bool => $record[0]->url() === 'https://api.gapgpt.app/v1/images/edits')
            ->values();

        $this->assertCount(3, $gapGptRequests);

        $prompts = $gapGptRequests
            ->map(fn (array $record) => $this->multipartField($record[0]->body(), 'prompt'))
            ->filter()
            ->values()
            ->all();

        $this->assertCount(3, array_unique($prompts));
        $this->assertTrue(collect($prompts)->every(fn (string $prompt): bool => str_contains($prompt, 'exactly one')));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => $this->multipartField($record[0]->body(), 'model') === 'gpt-image-2'));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => $this->multipartField($record[0]->body(), 'n') === '1'));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => $this->multipartField($record[0]->body(), 'response_format') === 'url'));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => $this->multipartField($record[0]->body(), 'size') === '1024x1024'));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => str_contains($record[0]->body(), 'name="image"; filename=')));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => $record[0]->header('Authorization') === ['Bearer gap-secret']));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => str_contains($record[0]->header('Content-Type')[0] ?? '', 'multipart/form-data; boundary=')));
        $this->assertTrue($gapGptRequests->every(fn (array $record): bool => ! str_contains($record[0]->header('Content-Type')[0] ?? '', 'application/json')));

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();
        $this->assertSame(['two_weeks', 'intermediate_regrowth', 'final_result'], $group->jobs()->orderBy('id')->pluck('period')->all());
        $this->assertSame(3, GeneratedAsset::query()->where('generation_group_id', $group->id)->count());
        $this->assertSame(3, $access->refresh()->credit_used);
    }

    public function test_gapgpt_http_errors_are_written_to_laravel_log(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        Log::spy();

        AiProvider::query()->firstOrCreate(
            ['code' => 'gapgpt'],
            [
                'name' => 'GapGPT',
                'type' => 'external',
                'base_url' => 'https://api.gapgpt.app/v1/images/edits',
                'auth_type' => 'bearer',
                'is_active' => true,
                'supports_async' => false,
            ]
        );

        $access = AiModelAccess::create([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT Error Access',
            'credentials' => ['api_key' => 'gap-secret'],
            'settings' => [
                'endpoint_url' => 'https://api.gapgpt.app/v1/images/edits',
                'model' => 'gpt-image-2',
                'size' => '1024x1024',
                'response_format' => 'url',
            ],
            'prompt' => '{stage_prompt}',
            'credit_limit' => 3,
            'is_active' => true,
            'priority' => 0,
        ]);

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $access->id => ['is_active' => true],
        ]);

        Http::fake([
            'https://api.gapgpt.app/v1/images/edits' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401, ['x-request-id' => 'gap-error-request']),
        ]);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124442222',
            'phone_normalized' => '09124442222',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $uploadId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertOk()
            ->json('upload.id');

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'failed')
            ->assertJsonPath('jobs.0.status', 'failed');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'GapGPT image edit request failed with HTTP error.'
                && ($context['provider'] ?? null) === 'gapgpt'
                && ($context['http_status'] ?? null) === 401
                && ($context['request_id'] ?? null) === 'gap-error-request'
                && ($context['endpoint_host'] ?? null) === 'api.gapgpt.app'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'gap-secret'))
            ->once();
    }

    public function test_generation_dispatches_only_the_first_stage_initially(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        Queue::fake();

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09125551111',
            'phone_normalized' => '09125551111',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $uploadId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertOk()
            ->json('upload.id');

        $groupId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'processing')
            ->assertJsonPath('jobs.0.status', 'processing')
            ->assertJsonPath('jobs.0.period', 'two_weeks')
            ->assertJsonPath('jobs.1.period', 'intermediate_regrowth')
            ->assertJsonPath('jobs.2.period', 'final_result')
            ->json('generation_group.id');

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();

        $this->assertSame(1, $group->jobs()->where('status', 'processing')->count());
        $this->assertSame(2, $group->jobs()->where('status', 'pending')->count());
        Queue::assertPushed(ProcessGenerationJob::class, 1);
        Queue::assertPushed(ProcessGenerationJob::class, fn (ProcessGenerationJob $job): bool => $job->generationJob->period === 'two_weeks');
    }

    public function test_tenant_b_cannot_access_tenant_a_assets(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');

        $leadA = Lead::create([
            'tenant_id' => $tenantA->id,
            'phone' => '09111111111',
            'phone_normalized' => '09111111111',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);
        $leadB = Lead::create([
            'tenant_id' => $tenantB->id,
            'phone' => '09222222222',
            'phone_normalized' => '09222222222',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);
        $upload = Upload::create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'original_disk' => 'local',
            'original_path' => 'uploads/a.jpg',
            'mime' => 'image/jpeg',
            'size' => 1,
            'status' => 'validated',
        ]);
        $group = GenerationGroup::create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'upload_id' => $upload->id,
            'status' => 'completed',
        ]);
        $asset = GeneratedAsset::create([
            'generation_group_id' => $group->id,
            'generation_job_id' => $this->createCompletedGenerationJob($tenantA, $leadA, $upload, $group)->id,
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'period' => 'two_weeks',
            'disk' => 'local',
            'path' => 'tenants/'.$tenantA->uuid.'/leads/'.$leadA->uuid.'/results/'.$group->uuid.'/two_weeks.jpg',
            'mime' => 'image/jpeg',
            'size' => 1,
        ]);

        Storage::disk('local')->put($asset->path, base64_decode(self::TEST_JPEG_BASE64));

        $this->withSession(['lead_id' => $leadB->id])
            ->forHost('clinic2.hairvision-ai.test')
            ->get('/api/v1/assets/'.$asset->uuid)
            ->assertNotFound();
    }

    public function test_theme_back_link_and_crm_lead_notes_are_tenant_owned(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $template = Template::create([
            'code' => 'clinic',
            'name' => 'Clinic',
            'is_active' => true,
        ]);

        $theme = TenantThemeSetting::create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'primary_color' => '#0f766e',
            'header_html' => '<p>Clinic Header</p>',
            'footer_html' => '<p>Clinic Footer</p>',
            'back_link_title' => 'Back to clinic',
            'back_link_url' => 'https://clinic.example.test',
        ]);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124444444',
            'phone_normalized' => '09124444444',
            'phone_verified_at' => now(),
            'status' => 'verified',
            'crm_stage' => 'needs_follow_up',
            'crm_last_note_at' => now(),
        ]);

        $note = LeadNote::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'type' => 'admin_note',
            'note' => 'Call this lead tomorrow.',
        ]);

        $this->assertSame('Back to clinic', $theme->back_link_title);
        $this->assertSame('needs_follow_up', $lead->crm_stage);
        $this->assertSame($tenant->id, $note->tenant_id);
        $this->assertSame($lead->id, $note->lead_id);
    }

    private function createTenant(string $name, string $slug, string $domain): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        $provider = AiProvider::query()->firstOrCreate(
            ['code' => 'sample_image'],
            [
                'name' => 'Sample Image Provider',
                'type' => 'local',
                'is_active' => true,
                'supports_async' => false,
            ]
        );

        TenantAiSetting::create([
            'tenant_id' => $tenant->id,
            'provider_id' => $provider->id,
            'is_default' => true,
        ]);

        $access = AiModelAccess::query()->firstOrCreate(
            [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => 'Test Sample Access',
            ],
            [
                'credentials' => [],
                'prompt' => 'Sample hair growth preview',
                'credit_limit' => null,
                'is_active' => true,
                'is_demo' => true,
                'is_protected' => true,
                'priority' => 1,
            ]
        );

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $access->id => ['is_active' => true],
        ]);

        return $tenant;
    }

    private function requestOtpAndReturnCode(string $host, string $phone): string
    {
        $this->enableIppanelOtpGateway();

        $payload = $this->forHost($host)
            ->postJson('/api/v1/auth/otp/request', ['phone' => $phone])
            ->assertOk()
            ->json();

        $this->assertArrayNotHasKey('debug_sms', $payload);

        return $this->latestSentOtpCode();
    }

    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }

    private function enableIppanelOtpGateway(): SmsGateway
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        return SmsGateway::query()->firstOrCreate(
            [
                'provider_code' => 'ippanel',
                'owner_scope' => 'system',
                'user_id' => null,
            ],
            [
                'name' => 'IPPanel System',
                'status' => 'enabled',
                'is_default' => false,
                'encrypted_credentials' => ['api_key' => 'system-token'],
                'settings_json' => [
                    'from_number' => '+983000505',
                    'otp_pattern_code' => 's5jh64gwzlk0jf3',
                ],
            ]
        );
    }

    private function latestSentOtpCode(): string
    {
        $record = Http::recorded()->last();
        $request = $record[0] ?? null;

        $code = $request['params']['verification-code'] ?? null;

        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        return $code;
    }

    private function fakeImageUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hairvision-test-image');
        file_put_contents($path, base64_decode(self::TEST_JPEG_BASE64));

        return new UploadedFile($path, 'scalp.jpg', 'image/jpeg', null, true);
    }

    private function seedSampleImages(): void
    {
        foreach (['two-weeks.jpg', 'intermediate_regrowth.jpg', 'final_result.jpg'] as $filename) {
            Storage::disk('local')->put('sample-results/'.$filename, base64_decode(self::TEST_JPEG_BASE64));
        }
    }

    private function multipartField(string $body, string $field): ?string
    {
        $pattern = '/name="'.preg_quote($field, '/').'"\RContent-Length: \d+\R\R(?P<value>.*?)\R--/s';

        if (! preg_match($pattern, $body, $matches)) {
            return null;
        }

        return trim((string) $matches['value']);
    }

    private function activateTenantSubscription(Tenant $tenant, int $credit = 100): Subscription
    {
        $user = User::factory()->create([
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);
        $tenant->users()->attach($user->id, ['role' => 'clinic_admin', 'status' => 'active']);

        $plan = Plan::query()->create([
            'name' => 'پلن تست سرویس',
            'description' => 'پلن تست سرویس',
            'plan_type' => 'base_subscription',
            'service_key' => 'hair_transplant',
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => 30,
            'usage_limit' => $credit,
            'status' => 'active',
        ]);

        $plan->serviceEntitlements()->create([
            'service_key' => 'hair_transplant',
            'credit_amount' => $credit,
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => $credit,
            'usage_used' => 0,
        ]);

        $entitlement = SubscriptionServiceEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'service_key' => 'hair_transplant',
            'source_type' => 'base_plan',
            'source_id' => $plan->id,
            'included_credit' => $credit,
            'purchased_credit' => 0,
            'used_credit' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        CreditTransaction::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_service_entitlement_id' => $entitlement->id,
            'service_key' => 'hair_transplant',
            'type' => 'grant',
            'amount' => $credit,
            'balance_after' => $credit,
        ]);

        return $subscription;
    }

    private function createCompletedGenerationJob(Tenant $tenant, Lead $lead, Upload $upload, GenerationGroup $group): GenerationJob
    {
        $provider = AiProvider::query()->where('code', 'sample_image')->firstOrFail();

        return $group->jobs()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'upload_id' => $upload->id,
            'provider_id' => $provider->id,
            'period' => 'two_weeks',
            'status' => 'completed',
            'prompt' => 'Sample',
            'completed_at' => now(),
        ]);
    }
}
