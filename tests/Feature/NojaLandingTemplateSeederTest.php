<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\Tenant;
use App\Rules\InlineLandingTemplateHtml;
use App\Services\Landing\LandingTemplateResolver;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\TenantSeeder;
use Database\Seeders\TenantThemeSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Verifies seeder-owned landing templates and the seeded Noja clinic assignment.
 *
 * @since 2.1.0
 */
class NojaLandingTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the Noja template is valid, responsive, image-self-contained, and assigned only to Noja.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_noja_template_is_seeded_and_assigned_to_the_noja_clinic(): void
    {
        $this->seed([
            TenantSeeder::class,
            TemplateSeeder::class,
            TenantThemeSettingSeeder::class,
        ]);

        $template = Template::query()->where('code', TemplateSeeder::NOJA_CODE)->firstOrFail();
        $classic = Template::query()->where('code', TemplateSeeder::CLASSIC_CODE)->firstOrFail();
        $noja = Tenant::query()->where('slug', 'noja')->firstOrFail();
        $arya = Tenant::query()->where('slug', 'arya')->firstOrFail();

        $this->assertTrue($template->is_active);
        $this->assertFalse($template->is_default);
        $this->assertSame(TemplateSeeder::classicContent()['header_html'], $classic->header_html);
        $this->assertSame(TemplateSeeder::nojaContent()['header_html'], $template->header_html);
        $this->assertSame(TemplateSeeder::nojaContent()['footer_html'], $template->footer_html);
        $this->assertSame($template->id, $noja->theme()->value('template_id'));
        $this->assertTrue((bool) $noja->theme()->value('use_template_content'));
        $this->assertSame($classic->id, $arya->theme()->value('template_id'));
        $this->assertFalse((bool) $arya->theme()->value('use_template_content'));

        $this->assertStringContainsString('fusion-tb-header noja-template-header', (string) $template->header_html);
        $this->assertStringContainsString('fusion-tb-footer noja-template-footer', (string) $template->footer_html);
        $this->assertStringContainsString('@media (max-width: 900px)', (string) $template->header_html);
        $this->assertStringContainsString('@media (max-width: 680px)', (string) $template->footer_html);
        $this->assertStringContainsString('class="noja-footer-brand-link"', (string) $template->footer_html);
        $this->assertStringContainsString('margin-inline: 0 auto;', (string) $template->footer_html);
        $this->assertStringContainsString('src="data:image/svg+xml;base64,', (string) $template->header_html);
        $this->assertStringContainsString('data:image/webp;base64,', (string) $template->footer_html);
        $this->assertStringContainsString('data-origin-url="https://nojakasht.com/', (string) $template->header_html);
        $this->assertStringContainsString('data-origin-url="https://nojakasht.com/', (string) $template->footer_html);
        $this->assertStringNotContainsString('src="https://', (string) $template->header_html);
        $this->assertStringNotContainsString('src="https://', (string) $template->footer_html);
        $this->assertLessThanOrEqual(250000, strlen((string) $template->header_html));
        $this->assertLessThanOrEqual(250000, strlen((string) $template->footer_html));
        $this->assertSame(69400, strlen((string) $template->header_html));
        $this->assertSame('44b5d0e9a8fc699320a0e1b3003123f2a74d86b983fbe5e73c79639b345b521f', hash('sha256', (string) $template->header_html));
        $this->assertSame(173290, strlen((string) $template->footer_html));
        $this->assertSame('deb601c3c97c567edc319f7349ccb12709438282a176b06900ef625bb31a6c75', hash('sha256', (string) $template->footer_html));

        $validator = Validator::make(
            [
                'header_html' => $template->header_html,
                'footer_html' => $template->footer_html,
            ],
            [
                'header_html' => [new InlineLandingTemplateHtml],
                'footer_html' => [new InlineLandingTemplateHtml],
            ],
        );

        $this->assertTrue($validator->passes(), $validator->errors()->toJson(JSON_UNESCAPED_UNICODE));

        $presentation = app(LandingTemplateResolver::class)->resolve($noja);

        $this->assertSame(TemplateSeeder::NOJA_CODE, $presentation['template_code']);
        $this->assertStringNotContainsString('{{ clinic.back_link.', $presentation['header_html']);
        $this->assertStringContainsString('href="https://nojakasht.com/"', $presentation['header_html']);

        $template->forceFill([
            'header_html' => '<header>نسخه قدیمی</header>',
            'footer_html' => '<footer>نسخه قدیمی</footer>',
        ])->save();

        $this->seed([TemplateSeeder::class, TenantThemeSettingSeeder::class]);

        $this->assertSame(1, Template::query()->where('code', TemplateSeeder::NOJA_CODE)->count());
        $this->assertSame($template->id, $noja->theme()->value('template_id'));
        $this->assertSame('44b5d0e9a8fc699320a0e1b3003123f2a74d86b983fbe5e73c79639b345b521f', hash('sha256', (string) $template->refresh()->header_html));
        $this->assertSame('deb601c3c97c567edc319f7349ccb12709438282a176b06900ef625bb31a6c75', hash('sha256', (string) $template->footer_html));
    }
}
