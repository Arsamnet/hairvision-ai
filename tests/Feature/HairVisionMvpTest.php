<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\Upload;
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
                'code' => $this->toPersianDigits($code),
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

        return $this->normalizeDigits($matches[1]);
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    private function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
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

    private function createCompletedGenerationJob(Tenant $tenant, Lead $lead, Upload $upload, GenerationGroup $group)
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
