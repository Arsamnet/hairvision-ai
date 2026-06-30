<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\AiModelAccess;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\CreditTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\SmsGateway;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\Upload;
use App\Models\User;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
        $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $this->forHost('clinic1.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'clinic1')
            ->assertJsonPath('features.ai_provider', 'sample_image');

        $this->forHost('unknown.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertNotFound();
    }

    public function test_landing_page_visit_is_tracked_before_any_lead_is_created(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $this->forHost('clinic1.hairvision-ai.test')
            ->get('/')
            ->assertOk();

        $this->assertSame(1, $tenant->landingPageVisits()->count());
        $this->assertDatabaseHas('tenant_landing_page_visits', [
            'tenant_id' => $tenant->id,
            'host' => 'clinic1.hairvision-ai.test',
        ]);
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_landing_page_uses_tenant_favicon_or_default_fallback(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $this->forHost('clinic1.hairvision-ai.test')
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

        $this->forHost('clinic1.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('href="/favicon.ico"', false)
            ->assertDontSee('/build/img/favicon.png', false);

        $tenant->theme()->update(['favicon_path' => '/theme/missing-favicon.png']);

        $this->forHost('clinic1.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('/build/assets/favicon-', false)
            ->assertDontSee('/theme/missing-favicon.png', false)
            ->assertDontSee('/build/img/favicon.png', false);

        $tenant->theme()->update(['favicon_path' => 'https://example.test/favicon.png']);

        $this->forHost('clinic1.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('/build/assets/favicon-', false)
            ->assertDontSee('https://example.test/favicon.png', false)
            ->assertDontSee('/build/img/favicon.png', false);
    }

    public function test_otp_request_stores_only_hash_and_is_rate_limited(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $first = $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $code = $this->extractDebugCode($first['debug_sms']);

        $otp = $tenant->otpCodes()->firstOrFail();
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));

        $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertOk();

        $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertStatus(429);
    }

    public function test_otp_request_sends_system_ippanel_pattern_when_configured(): void
    {
        $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);

        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['ok' => true]),
        ]);

        $payload = $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09124248164'])
            ->assertOk()
            ->json();

        $code = $this->extractDebugCode($payload['debug_sms']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($code): bool {
            return $request->url() === 'https://edge.ippanel.com/v1/api/send'
                && $request->header('Authorization') === ['system-token']
                && $request['sending_type'] === 'pattern'
                && $request['from_number'] === '+983000505'
                && $request['code'] === 's5jh64gwzlk0jf3'
                && $request['recipients'] === ['+989124248164']
                && $request['params']['verification-code'] === $code;
        });
    }

    public function test_otp_verify_creates_verified_lead_for_current_tenant(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $payload = $this->requestOtpAndReturnPayload('clinic1.hairvision-ai.test', '09123456789');
        $code = $this->extractDebugCode($payload['debug_sms']);

        $this->forHost('clinic1.hairvision-ai.test')
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
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $payload = $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '۰۹۱۲۳۴۵۶۷۸۹'])
            ->assertOk()
            ->assertJsonPath('message', 'کد تایید ارسال شد.')
            ->json();

        $code = $this->extractDebugCode($payload['debug_sms']);

        $this->forHost('clinic1.hairvision-ai.test')
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

        $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '0912abc6789'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'شماره موبایل باید فقط شامل ۱۱ رقم باشد.');

        $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '۰۹۱۲۳۴۵۶۷۸۹',
                'code' => '۱۲۳x۵۶',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'کد تایید باید فقط شامل ۶ رقم باشد.');
    }

    public function test_upload_requires_verified_lead_session_and_rejects_invalid_files(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');

        $this->forHost('clinic1.hairvision-ai.test')
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
            ->forHost('clinic1.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
                'consent' => '1',
            ])
            ->assertJsonValidationErrors('image');
    }

    public function test_sample_generation_flow_creates_three_private_assets_without_external_http(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        $this->seedSampleImages();
        Http::fake();

        $payload = $this->requestOtpAndReturnPayload('clinic1.hairvision-ai.test', '09123456789');
        $code = $this->extractDebugCode($payload['debug_sms']);

        $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/verify', [
                'phone' => '09123456789',
                'code' => $code,
            ])
            ->assertOk();

        $uploadId = $this->forHost('clinic1.hairvision-ai.test')
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('upload.status', 'validated')
            ->json('upload.id');

        $groupId = $this->forHost('clinic1.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'completed')
            ->assertJsonCount(3, 'jobs')
            ->json('generation_group.id');

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();
        $this->assertSame($tenant->id, $group->tenant_id);
        $this->assertSame('completed', $group->refresh()->status);
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

        $this->forHost('clinic1.hairvision-ai.test')
            ->getJson("/api/v1/generations/{$groupId}/status")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonCount(3, 'jobs');

        $results = $this->forHost('clinic1.hairvision-ai.test')
            ->getJson("/api/v1/generations/{$groupId}/results")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'results')
            ->json('results');

        $asset = GeneratedAsset::query()->firstOrFail();
        Storage::disk('local')->assertExists($asset->path);
        $this->assertStringStartsWith('tenants/'.$tenant->uuid.'/leads/', $asset->path);

        $this->forHost('clinic1.hairvision-ai.test')
            ->get($results[0]['url'])
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        Http::assertNothingSent();
    }

    public function test_tenant_b_cannot_access_tenant_a_assets(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
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
            'period' => '3_months',
            'disk' => 'local',
            'path' => 'tenants/'.$tenantA->uuid.'/leads/'.$leadA->uuid.'/results/'.$group->uuid.'/3_months.jpg',
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
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
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

    private function requestOtpAndReturnPayload(string $host, string $phone): array
    {
        return $this->forHost($host)
            ->postJson('/api/v1/auth/otp/request', ['phone' => $phone])
            ->assertOk()
            ->json();
    }

    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }

    private function extractDebugCode(string $debugSms): string
    {
        preg_match('/([0-9۰-۹٠-٩]{6})/u', $debugSms, $matches);

        return NumberNormalizer::toLatinDigits($matches[1]);
    }

    private function fakeImageUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hairvision-test-image');
        file_put_contents($path, base64_decode(self::TEST_JPEG_BASE64));

        return new UploadedFile($path, 'scalp.jpg', 'image/jpeg', null, true);
    }

    private function seedSampleImages(): void
    {
        foreach (['3-months.jpg', '6-months.jpg', '12-months.jpg'] as $filename) {
            Storage::disk('local')->put('sample-results/'.$filename, base64_decode(self::TEST_JPEG_BASE64));
        }
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
            'period' => '3_months',
            'status' => 'completed',
            'prompt' => 'Sample',
            'completed_at' => now(),
        ]);
    }
}
