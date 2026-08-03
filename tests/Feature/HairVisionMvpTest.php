<?php

namespace Tests\Feature;

use App\Jobs\ProcessGenerationJob;
use App\Models\AiModelAccess;
use App\Models\AiModelCategoryOutput;
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
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\Upload;
use App\Models\User;
use App\Services\Generation\GenerationGroupStatusService;
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

/**
 * Verifies the tenant-scoped public HairVision visitor workflow.
 *
 * @since 1.0.0
 * @since 2.1.0 Coverage expanded for landing usability and analytics behavior.
 * @since 2.1.1 Updates release-version asset URL expectations.
 */
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
            'hairvision.api_image.max_dimension' => 800,
            'hairvision.api_image.jpeg_quality' => 85,
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
            ->assertJsonPath('features.upload_enabled', true)
            ->assertJsonMissingPath('features.ai_provider');

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
            ->assertSee('.png?v=2.1.1', false)
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

    public function test_built_assets_use_content_hash_names_and_versioned_urls(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($manifest as $chunk) {
            $this->assertMatchesRegularExpression(
                '/\/[^\/]+-[a-zA-Z0-9_-]+\.[a-z0-9]+$/',
                (string) $chunk['file'],
            );
            $this->assertStringNotContainsString('-v2.1.1-', (string) $chunk['file']);
        }

        $content = $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->content();

        $cssFiles = [
            $manifest['resources/css/app.css']['file'],
            $manifest['resources/css/landing-icons.css']['file'],
            ...$manifest['resources/js/app.js']['css'],
        ];

        foreach ($cssFiles as $cssFile) {
            $versionedPath = '/build/'.$cssFile.'?v=2.1.1';
            $quotedPath = preg_quote($versionedPath, '/');

            $this->assertSame(
                1,
                preg_match_all('/<link rel="stylesheet" href="[^"]*'.$quotedPath.'"/', $content),
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<script[^>]+src="[^"]*'.$quotedPath.'"/',
                $content,
            );
            $this->assertDoesNotMatchRegularExpression(
                '/<link[^>]+rel="modulepreload"[^>]+href="[^"]*'.$quotedPath.'"/',
                $content,
            );
        }

        $javascriptPath = '/build/'.$manifest['resources/js/app.js']['file'].'?v=2.1.1';

        $this->assertSame(
            1,
            preg_match_all(
                '/<script type="module" src="[^"]*'.preg_quote($javascriptPath, '/').'"><\/script>/',
                $content,
            ),
        );
        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/build\/assets\/favicon-[a-zA-Z0-9_-]+\.png\?v=2\.1\.1"/',
            $content,
        );

        preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $content, $stylesheetMatches);
        preg_match_all('/<script type="module" src="([^"]+)"><\/script>/', $content, $scriptMatches);

        $this->assertSame($stylesheetMatches[1], array_values(array_unique($stylesheetMatches[1])));
        $this->assertSame($scriptMatches[1], array_values(array_unique($scriptMatches[1])));
    }

    public function test_otp_request_stores_only_hash_and_is_rate_limited(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        config(['hairvision.otp.expires_seconds' => 90]);
        $this->enableIppanelOtpGateway();

        $first = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('expires_in', 90)
            ->json();

        $this->assertArrayNotHasKey('debug_sms', $first);

        $code = $this->latestSentOtpCode();

        $otp = $tenant->otpCodes()->firstOrFail();
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertTrue($otp->expires_at->between(now()->addSeconds(80), now()->addSeconds(100)));

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertOk();

        $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09123456789'])
            ->assertStatus(429);
    }

    public function test_landing_page_renders_tenant_colored_otp_timer_and_resend_controls_without_logo(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $template = Template::create([
            'code' => 'clinic-login',
            'name' => 'Clinic Login',
            'is_active' => true,
        ]);

        TenantThemeSetting::create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'logo_path' => '/theme/logo.svg',
            'primary_color' => '#123456',
        ]);

        $response = $this->forHost('aria.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('--hv-primary-color: #123456;', false)
            ->assertDontSee('src="/theme/logo.svg"', false)
            ->assertSee('id="otpCountdown"', false)
            ->assertSee('id="resendOtp"', false)
            ->assertSee('id="phoneEntryPanel"', false)
            ->assertSee('id="otpEntryPanel" class="hidden', false)
            ->assertSee('id="mobileError"', false)
            ->assertSee('id="outputChoicePanel"', false)
            ->assertSee('id="outputOptions" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4"', false)
            ->assertSee('id="newImageUpload"', false)
            ->assertSee('آپلود تصویر جدید')
            ->assertSee('id="uploadGuidePanel"', false)
            ->assertSee('id="uploadGuideContent"', false)
            ->assertSee('id="stepProgressTrack"', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('class="hv-stepper"', false)
            ->assertSee('class="flow-step hv-enter mb-0!', false)
            ->assertSee('class="hv-upload-composer"', false)
            ->assertSee('id="uploadDropZone"', false)
            ->assertSee('id="imageSelectionPreview"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif"', false)
            ->assertSee('aria-labelledby="imageInputLabel" aria-describedby="imageRequirements imagePreparationStatus uploadError"', false)
            ->assertSee('JPG، PNG، WEBP، HEIC یا HEIF تا ۵ مگابایت')
            ->assertSee('تصاویر آیفون پیش از ارسال، داخل مرورگر به JPG تبدیل می‌شوند.')
            ->assertSee('id="imagePreparationStatus"', false)
            ->assertSee('id="uploadError" class="hidden', false)
            ->assertSee('role="alert" aria-live="assertive"', false)
            ->assertSee('class="hv-button hv-button-primary hv-action-control hv-mobile-action', false)
            ->assertSee('class="hv-button hv-button-secondary hv-button-compact inline-flex"', false)
            ->assertSee('@keyframes hv-ai-border-flow', false)
            ->assertSee('id="continueToPhone" type="button"', false)
            ->assertSee('disabled>ادامه و تایید شماره', false)
            ->assertSee('روی هر خروجی بزنید')
            ->assertSee('/theme/category-images/hair-transplant.jpg', false)
            ->assertSee('/theme/category-images/eyebrow-transplant.jpg', false)
            ->assertDontSee('/theme/landing/hair-ai-consultation.jpg', false)
            ->assertSee('ارسال مجدد کد')
            ->assertDontSee('ارسال مجدد تا')
            ->assertDontSee('id="sidebarTitle"', false)
            ->assertDontSee('hv-hero-panel', false)
            ->assertDontSee('id="resultsStatusBox"', false)
            ->assertSee('تغییر شماره')
            ->assertSee('id="downloadImageModal"', false)
            ->assertSee('data-gallery-download', false)
            ->assertSee('fi-rr-cross-small', false);

        $this->assertSame(1, substr_count($response->getContent(), 'id="generationError"'));
        $this->assertStringContainsString('#landingApp button:not(:disabled)', $response->getContent());
        $this->assertStringContainsString('cursor: pointer', $response->getContent());
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr));', $response->getContent());
        $this->assertMatchesRegularExpression('/\.hv-output-description,\s*\.hv-output-status\s*\{\s*display: none;/s', $response->getContent());
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $response->getContent());
        $this->assertMatchesRegularExpression('/<div id="landingApp"[^>]*>\s*<section>/', $response->getContent());
        $this->assertStringNotContainsString('.hv-app-shell', $response->getContent());
        $this->assertStringNotContainsString('hover:-translate-y-', $response->getContent());
        $this->assertStringNotContainsString('hover:scale-', $response->getContent());
        $this->assertStringNotContainsString('linear-gradient(135deg, #0f172a, #111827) padding-box', $response->getContent());
        $landingJavascript = file_get_contents(resource_path('js/landing.js'));

        $this->assertStringContainsString("label: 'تصویر اصلی'", $landingJavascript);
        $this->assertStringContainsString('generationDisplayItemsFromJobs', $landingJavascript);
        $this->assertStringContainsString('items.length > 0 ? originalGalleryItem()', $landingJavascript);
        $this->assertStringContainsString('category?.card_image_url', $landingJavascript);
        $this->assertStringContainsString('category?.upload_guide_html', $landingJavascript);
        $this->assertStringContainsString('sanitizeRichTextHtml', $landingJavascript);
        $this->assertStringContainsString('MAX_IMAGE_SIZE_BYTES', $landingJavascript);
        $this->assertStringContainsString("import('heic-to/csp')", $landingJavascript);
        $this->assertStringContainsString("'image/heic'", $landingJavascript);
        $this->assertStringContainsString("'image/heif'", $landingJavascript);
        $this->assertStringContainsString('STATE.selectedSourceFile', $landingJavascript);
        $this->assertStringContainsString('حجم فایل انتخاب‌شده', $landingJavascript);
        $this->assertStringContainsString('hasSourceImageSelection', $landingJavascript);
        $this->assertStringContainsString("['dragenter', 'dragover']", $landingJavascript);
        $this->assertStringContainsString('hv-output-option', $landingJavascript);
        $this->assertStringContainsString('hv-output-title', $landingJavascript);
        $this->assertStringContainsString('hv-output-description', $landingJavascript);
        $this->assertStringContainsString('hv-output-status', $landingJavascript);
        $this->assertStringContainsString("button.setAttribute('aria-pressed', selected ? 'true' : 'false')", $landingJavascript);
        $this->assertStringContainsString("if (nextStep.classList.contains('hidden'))", $landingJavascript);
        $this->assertStringContainsString('focus({ preventScroll: true })', $landingJavascript);
        $this->assertStringNotContainsString('window.scrollTo', $landingJavascript);
        $this->assertStringContainsString("continueButton.textContent = STATE.verified ? 'ادامه بدون تایید مجدد'", $landingJavascript);
        $this->assertStringContainsString('await beginGenerationProcess(continueButton)', $landingJavascript);
        $this->assertStringContainsString("sessionStorage.getItem(STORAGE_KEYS.verified) === '1'", $landingJavascript);
        $this->assertStringContainsString('STATE.verified = STATE.serverSessionVerified && browserSessionVerified', $landingJavascript);
        $this->assertStringContainsString('const hydrated = STATE.verified ? await hydrateVerifiedProfile() : false', $landingJavascript);
        $this->assertStringContainsString("document.getElementById('newImageUpload').addEventListener('click', startNewImageJourney)", $landingJavascript);
        $this->assertStringContainsString('function cancelPolling()', $landingJavascript);
        $this->assertStringContainsString("if (pollSequence !== STATE.pollSequence) return 'background'", $landingJavascript);
        $this->assertStringContainsString('refreshActiveGenerations(expectedPollSequence = null)', $landingJavascript);
        $this->assertStringContainsString('expectedPollSequence !== STATE.pollSequence', $landingJavascript);
        $this->assertStringContainsString('hv-button hv-button-secondary hv-button-compact', $landingJavascript);
        $this->assertStringContainsString('hv-result-card hv-enter flex h-full flex-col', $landingJavascript);
        $this->assertStringContainsString('data-result-card-body', $landingJavascript);
        $this->assertStringContainsString('class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-3 py-2.5" data-result-card-footer', $landingJavascript);
        $this->assertMatchesRegularExpression('/<footer[^>]+data-result-card-footer>\s*\$\{status\}\s*\$\{downloadAction\}\s*<\/footer>/', $landingJavascript);
        $this->assertStringNotContainsString('hover:-translate-y-', $landingJavascript);
        $this->assertStringContainsString('در حال ساخت', $landingJavascript);
        $this->assertLessThan(
            strpos($landingJavascript, "import('heic-to/csp')"),
            strpos($landingJavascript, 'file.size > MAX_IMAGE_SIZE_BYTES'),
            'The browser must reject oversized files before loading the HEIC converter or starting an upload.',
        );

        preg_match('/function resetGenerationProgress\(.*?(?=\n    function startNewImageJourney)/s', $landingJavascript, $resetGenerationMatch);
        $this->assertNotEmpty($resetGenerationMatch);
        $this->assertStringNotContainsString('STATE.sourceImageId = null', $resetGenerationMatch[0]);
        $this->assertMatchesRegularExpression('/function startNewImageJourney\(\).*?STATE\.sourceImageId = null/s', $landingJavascript);

        preg_match("/document\.getElementById\('continueToPhone'\)\.addEventListener\('click'.*?document\.getElementById\('newImageUpload'\)/s", $landingJavascript, $verifiedReuseMatch);
        $this->assertNotEmpty($verifiedReuseMatch);
        $this->assertStringContainsString('if (STATE.verified)', $verifiedReuseMatch[0]);
        $this->assertLessThan(
            strpos($verifiedReuseMatch[0], 'UI.showStep(3)'),
            strpos($verifiedReuseMatch[0], 'await beginGenerationProcess(continueButton)'),
            'A verified visitor must upload the next image before the OTP-step fallback is considered.',
        );
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

    public function test_otp_request_returns_only_a_generic_error_after_three_failed_sms_attempts(): void
    {
        $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');

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

        config(['hairvision.sms.ippanel.retry_delay_milliseconds' => 0]);
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response([
                'meta' => [
                    'status' => false,
                    'message' => 'Provider internal failure',
                ],
            ], 503),
        ]);

        $response = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/auth/otp/request', ['phone' => '09124248164'])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sms.delivery_failed_generic'));

        $encodedPayload = json_encode($response->json(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('IPPanel', $encodedPayload);
        $this->assertStringNotContainsString('sms_gateway', $encodedPayload);
        $this->assertStringNotContainsString('Provider internal failure', $encodedPayload);
        Http::assertSentCount(3);
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
        $this->assertSame('new', $lead->status);
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

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/profile/source-image', [
                'image' => UploadedFile::fake()->image('too-large.jpg', 100, 100)->size(5121),
                'consent' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image')
            ->assertJsonPath('errors.image.0', 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.');

        $this->assertDatabaseCount('lead_source_images', 0);
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
            'phone_normalized' => '09124248164',
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
            ->assertStatus(202)
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
            ->assertStatus(202)
            ->json('generation_group.id');

        $this->assertSame($groupId, $repeatGroupId);
        $this->assertSame(1, GenerationGroup::query()->where('upload_id', $group->upload_id)->count());
    }

    public function test_eyebrow_generation_creates_one_private_asset_with_sample_model(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $subscription = $this->activateTenantSubscription($tenant, 5);
        $this->seedSampleImages();
        Http::fake();

        $access = AiModelAccess::create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'eyebrow_transplant',
            'name' => 'Eyebrow Sample Access',
            'credentials' => [],
            'settings' => [],
            'prompt' => 'Eyebrow sample prompt',
            'credit_limit' => null,
            'is_active' => true,
            'is_demo' => true,
            'is_protected' => false,
            'priority' => 0,
        ]);

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $access->id => ['is_active' => true],
        ]);

        $eyebrowEntitlement = SubscriptionServiceEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'service_key' => 'eyebrow_transplant',
            'source_type' => 'base_plan',
            'source_id' => $subscription->plan_id,
            'included_credit' => 5,
            'purchased_credit' => 0,
            'used_credit' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        CreditTransaction::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_service_entitlement_id' => $eyebrowEntitlement->id,
            'service_key' => 'eyebrow_transplant',
            'type' => 'grant',
            'amount' => 5,
            'balance_after' => 5,
        ]);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124448888',
            'phone_normalized' => '09124448888',
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
            ->postJson('/api/v1/generations', [
                'upload_id' => $uploadId,
                'usage_category_key' => 'eyebrow_transplant',
            ])
            ->assertStatus(202)
            ->assertJsonPath('generation_group.status', 'completed')
            ->assertJsonPath('stages.0.key', 'eyebrow_final_result')
            ->assertJsonPath('jobs.0.period', 'eyebrow_final_result')
            ->assertJsonCount(1, 'stages')
            ->assertJsonCount(1, 'jobs')
            ->json('generation_group.id');

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();

        $this->assertSame(['eyebrow_final_result'], $group->jobs()->pluck('period')->all());
        $this->assertSame(1, GeneratedAsset::query()->where('generation_group_id', $group->id)->count());
        $this->assertSame(1, $access->refresh()->usage_count);
        $this->assertSame(0, $access->credit_used);
        $this->assertSame(4, $eyebrowEntitlement->refresh()->remainingCredit());
        $this->assertSame(1, CreditTransaction::query()->where('tenant_id', $tenant->id)->where('service_key', 'eyebrow_transplant')->where('type', 'use')->count());

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->getJson("/api/v1/generations/{$groupId}/results")
            ->assertOk()
            ->assertJsonPath('results.0.period', 'eyebrow_final_result')
            ->assertJsonCount(1, 'results');

        Http::assertNothingSent();
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
            'credit_limit' => 3,
            'is_active' => true,
            'priority' => 0,
        ]);

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $access->id => ['is_active' => true],
        ]);

        $providerImage = $this->patternedJpeg(1600, 800);

        Http::fake([
            'https://api.gapgpt.app/v1/images/edits' => Http::response([
                'id' => 'gap-response',
                'data' => [
                    ['url' => 'https://cdn.gapgpt.test/generated/result.jpg'],
                ],
            ], 200, ['x-request-id' => 'gap-request']),
            'https://cdn.gapgpt.test/generated/result.jpg' => Http::response($providerImage, 200, [
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
            ->assertStatus(202)
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

        $expectedPrompts = AiModelCategoryOutput::query()
            ->whereHas('category', fn ($query) => $query->where('code', 'hair_transplant'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('prompt')
            ->all();

        $this->assertSame($expectedPrompts, $prompts);
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
        GeneratedAsset::query()
            ->where('generation_group_id', $group->id)
            ->get()
            ->each(function (GeneratedAsset $asset): void {
                $storedImage = Storage::disk($asset->disk)->get($asset->path);
                $imageInfo = getimagesizefromstring($storedImage);

                $this->assertIsArray($imageInfo);
                $this->assertSame([800, 400], [$imageInfo[0], $imageInfo[1]]);
                $this->assertSame(IMAGETYPE_JPEG, $imageInfo[2]);
                $this->assertSame('image/jpeg', $asset->mime);
                $this->assertStringEndsWith('.jpg', $asset->path);
                $this->assertSame(strlen($storedImage), $asset->size);
                $this->assertSame(hash('sha256', $storedImage), $asset->checksum);
            });
        $this->assertSame(3, $access->refresh()->credit_used);
    }

    public function test_gapgpt_generation_dispatches_all_independent_stages_initially(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        Queue::fake();

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
            'name' => 'GapGPT Concurrent Access',
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

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124443333',
            'phone_normalized' => '09124443333',
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
            ->assertStatus(202)
            ->assertJsonPath('generation_group.status', 'queued')
            ->assertJsonPath('jobs.0.status', 'queued')
            ->assertJsonPath('jobs.1.status', 'queued')
            ->assertJsonPath('jobs.2.status', 'queued')
            ->json('generation_group.id');

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();

        $this->assertSame(3, $group->jobs()->where('status', 'queued')->count());
        Queue::assertPushed(ProcessGenerationJob::class, 3);
        foreach (['two_weeks', 'intermediate_regrowth', 'final_result'] as $period) {
            Queue::assertPushed(
                ProcessGenerationJob::class,
                fn (ProcessGenerationJob $job): bool => $job->generationJob->period === $period,
            );
        }
    }

    public function test_gapgpt_stage_failure_does_not_cancel_remaining_stages(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        config(['app.debug' => false]);
        Queue::fake();
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
            'name' => 'GapGPT Partial Failure Access',
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

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124445555',
            'phone_normalized' => '09124445555',
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
            ->assertStatus(202)
            ->assertJsonPath('generation_group.status', 'queued')
            ->json('generation_group.id');

        Http::fake([
            'https://api.gapgpt.app/v1/images/edits' => Http::response([
                'error' => ['message' => 'Invalid API key'],
            ], 401, ['x-request-id' => 'gap-auth-request']),
        ]);

        $group = GenerationGroup::query()->where('uuid', $groupId)->with('jobs')->firstOrFail();
        $firstJob = $group->jobs->firstWhere('period', 'two_weeks');

        $this->assertInstanceOf(GenerationJob::class, $firstJob);

        (new ProcessGenerationJob($firstJob))->handle();

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->getJson('/api/v1/generations/'.$groupId.'/status')
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('jobs.0.status', 'failed')
            ->assertJsonPath('jobs.1.status', 'queued')
            ->assertJsonPath('jobs.2.status', 'queued')
            ->assertJsonPath('jobs.0.error', __('ai_models.errors.provider_failed'))
            ->assertJsonPath('jobs.1.error', null)
            ->assertJsonPath('jobs.2.error', null);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Generation job failed.'
                && ($context['period'] ?? null) === 'two_weeks'
                && ($context['reason_code'] ?? null) === 'provider_client_error'
                && ($context['model_key'] ?? null) === 'gapgpt'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'gap-secret'))
            ->once();
    }

    public function test_gapgpt_transient_http_errors_fail_without_repeating_provider_request(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 10);
        Queue::fake();
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
            'name' => 'GapGPT Retry Access',
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

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09124445556',
            'phone_normalized' => '09124445556',
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
            ->assertStatus(202)
            ->assertJsonPath('generation_group.status', 'queued')
            ->json('generation_group.id');

        Http::fake([
            'https://api.gapgpt.app/v1/images/edits' => Http::response([
                'error' => ['message' => 'Upstream timeout'],
            ], 504, ['x-request-id' => 'gap-timeout-request']),
        ]);

        $group = GenerationGroup::query()->where('uuid', $groupId)->with('jobs')->firstOrFail();
        $firstJob = $group->jobs->firstWhere('period', 'two_weeks');

        $this->assertInstanceOf(GenerationJob::class, $firstJob);

        (new ProcessGenerationJob($firstJob))->handle();

        $firstJob->refresh();
        $group->refresh();

        $gapGptRequests = Http::recorded()
            ->filter(fn (array $record): bool => $record[0]->url() === 'https://api.gapgpt.app/v1/images/edits')
            ->values();

        $this->assertCount(1, $gapGptRequests);
        $this->assertSame('queued', $group->status);
        $this->assertSame('failed', $firstJob->status);
        $this->assertSame(1, $firstJob->attempts);
        $this->assertSame(__('ai_models.errors.provider_failed'), $firstJob->last_error);
        $this->assertSame(0, CreditTransaction::query()->where('related_id', $firstJob->id)->count());

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->getJson('/api/v1/generations/'.$groupId.'/status')
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('jobs.0.status', 'failed')
            ->assertJsonPath('jobs.1.status', 'queued')
            ->assertJsonPath('jobs.2.status', 'queued')
            ->assertJsonPath('jobs.0.error', __('ai_models.errors.provider_failed'))
            ->assertJsonPath('jobs.1.error', null)
            ->assertJsonPath('jobs.2.error', null);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Generation job failed.'
                && ($context['period'] ?? null) === 'two_weeks'
                && ($context['reason_code'] ?? null) === 'provider_server_error'
                && ($context['model_key'] ?? null) === 'gapgpt'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'gap-secret'))
            ->once();
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
            ->assertStatus(202)
            ->assertJsonPath('generation_group.status', 'failed')
            ->assertJsonPath('jobs.0.status', 'failed')
            ->assertJsonPath('jobs.1.status', 'failed')
            ->assertJsonPath('jobs.2.status', 'failed')
            ->assertJsonPath('jobs.0.error', __('ai_models.errors.provider_failed'))
            ->assertJsonPath('jobs.1.error', __('ai_models.errors.provider_failed'))
            ->assertJsonPath('jobs.2.error', __('ai_models.errors.provider_failed'));

        $gapGptRequests = Http::recorded()
            ->filter(fn (array $record): bool => $record[0]->url() === 'https://api.gapgpt.app/v1/images/edits')
            ->values();

        $this->assertCount(3, $gapGptRequests);
        $this->assertSame(0, $access->refresh()->credit_used);
        $this->assertSame(0, CreditTransaction::query()->where('tenant_id', $tenant->id)->where('type', 'use')->count());
        $this->assertDatabaseHas('ai_model_usage_logs', [
            'ai_model_access_id' => $access->id,
            'usage_category_key' => 'hair_transplant',
            'status' => 'failed',
            'credit_consumed' => 0,
        ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'GapGPT image edit request failed with HTTP error.'
                && ($context['provider'] ?? null) === 'gapgpt'
                && ($context['http_status'] ?? null) === 401
                && ($context['request_id'] ?? null) === 'gap-error-request'
                && ($context['endpoint_host'] ?? null) === 'api.gapgpt.app'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'gap-secret'))
            ->times(3);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Generation job failed.'
                && ($context['period'] ?? null) === 'two_weeks'
                && ($context['reason_code'] ?? null) === 'provider_client_error'
                && ($context['model_key'] ?? null) === 'gapgpt'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'gap-secret'))
            ->once();
    }

    public function test_generation_dispatches_all_independent_stages_initially(): void
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
            ->assertStatus(202)
            ->assertJsonPath('generation_group.status', 'queued')
            ->assertJsonPath('jobs.0.status', 'queued')
            ->assertJsonPath('jobs.0.period', 'two_weeks')
            ->assertJsonPath('jobs.1.period', 'intermediate_regrowth')
            ->assertJsonPath('jobs.2.period', 'final_result')
            ->json('generation_group.id');

        $group = GenerationGroup::query()->where('uuid', $groupId)->firstOrFail();

        $this->assertSame(3, $group->jobs()->where('status', 'queued')->count());
        Queue::assertPushed(ProcessGenerationJob::class, 3);
        foreach (['two_weeks', 'intermediate_regrowth', 'final_result'] as $period) {
            Queue::assertPushed(
                ProcessGenerationJob::class,
                fn (ProcessGenerationJob $job): bool => $job->generationJob->period === $period,
            );
        }
    }

    public function test_outputs_and_different_users_requests_have_independent_failure_state(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant, 20);
        Queue::fake();

        $firstLead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09125550001',
            'phone_normalized' => '09125550001',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);
        $secondLead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09125550002',
            'phone_normalized' => '09125550002',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $firstUpload = $this->withSession(['lead_id' => $firstLead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', ['image' => $this->fakeImageUpload(), 'consent' => '1'])
            ->assertOk()
            ->json('upload.id');
        $secondUpload = $this->withSession(['lead_id' => $secondLead->id])
            ->forHost('aria.hairvision-ai.test')
            ->post('/api/v1/uploads', ['image' => $this->fakeImageUpload(), 'consent' => '1'])
            ->assertOk()
            ->json('upload.id');

        $firstGroupId = $this->withSession(['lead_id' => $firstLead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $firstUpload])
            ->assertStatus(202)
            ->json('generation_group.id');
        $secondGroupId = $this->withSession(['lead_id' => $secondLead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $secondUpload])
            ->assertStatus(202)
            ->json('generation_group.id');

        $firstGroup = GenerationGroup::query()->where('uuid', $firstGroupId)->firstOrFail();
        $secondGroup = GenerationGroup::query()->where('uuid', $secondGroupId)->firstOrFail();
        $firstGroup->jobs()->orderBy('id')->firstOrFail()->forceFill([
            'status' => 'failed',
            'error_code' => 'provider_client_error',
            'completed_at' => now(),
        ])->save();
        app(GenerationGroupStatusService::class)->refresh($firstGroup);

        $this->assertSame('queued', $firstGroup->refresh()->status);
        $this->assertSame(2, $firstGroup->jobs()->where('status', 'queued')->count());
        $this->assertSame('queued', $secondGroup->refresh()->status);
        $this->assertSame(3, $secondGroup->jobs()->where('status', 'queued')->count());
        Queue::assertPushed(ProcessGenerationJob::class, 6);
    }

    public function test_generation_job_without_assigned_model_fails_without_provider_fallback(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $tenant->aiModelAccesses()->detach();

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09125552222',
            'phone_normalized' => '09125552222',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $upload = Upload::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'original_disk' => 'local',
            'original_path' => 'uploads/unassigned-model.jpg',
            'mime' => 'image/jpeg',
            'size' => 1,
            'status' => 'validated',
        ]);

        $group = GenerationGroup::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'upload_id' => $upload->id,
            'status' => 'processing',
        ]);

        $job = GenerationJob::create([
            'generation_group_id' => $group->id,
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'upload_id' => $upload->id,
            'provider_id' => AiProvider::query()->where('code', 'sample_image')->firstOrFail()->id,
            'ai_model_access_id' => null,
            'period' => 'two_weeks',
            'status' => 'processing',
            'model_name' => null,
            'prompt' => 'Test prompt',
        ]);

        try {
            (new ProcessGenerationJob($job))->handle();
            $this->fail('A generation job without an assigned clinic model must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Assigned clinic AI model could not be resolved.', $exception->getMessage());
        }

        $this->assertSame('failed', $job->refresh()->status);
        $this->assertSame('failed', $group->refresh()->status);
        $this->assertDatabaseMissing('generated_assets', ['generation_job_id' => $job->id]);
        Http::assertNothingSent();
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
            'status' => 'needs_follow_up',
            'crm_last_note_at' => now(),
        ]);

        $note = LeadNote::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'type' => 'admin_note',
            'note' => 'Call this lead tomorrow.',
        ]);

        $this->assertSame('Back to clinic', $theme->back_link_title);
        $this->assertSame('needs_follow_up', $lead->status);
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

        AiProvider::query()->firstOrCreate(
            ['code' => 'sample_image'],
            [
                'name' => 'Sample Image Provider',
                'type' => 'local',
                'is_active' => true,
                'supports_async' => false,
            ]
        );

        $access = AiModelAccess::query()->firstOrCreate(
            [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => 'Test Sample Access',
            ],
            [
                'credentials' => [],
                'credit_limit' => null,
                'is_active' => true,
                'is_demo' => true,
                'is_protected' => false,
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
        $sampleImage = $this->patternedJpeg(1600, 800);

        foreach (['two-weeks.jpg', 'intermediate_regrowth.jpg', 'final_result.jpg', 'eyebrow_final_result.jpg'] as $filename) {
            Storage::disk('local')->put('sample-results/'.$filename, $sampleImage);
        }
    }

    private function patternedJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y += 20) {
            for ($x = 0; $x < $width; $x += 20) {
                $color = imagecolorallocate(
                    $image,
                    ($x * 13 + $y * 3) % 256,
                    ($x * 5 + $y * 17) % 256,
                    ($x * 19 + $y * 7) % 256,
                );
                imagefilledrectangle($image, $x, $y, min($x + 19, $width - 1), min($y + 19, $height - 1), $color);
            }
        }

        ob_start();
        imagejpeg($image, null, 95);
        $binary = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($binary);

        return $binary;
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
