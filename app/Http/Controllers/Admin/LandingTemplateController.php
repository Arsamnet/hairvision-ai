<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LandingTemplates\StoreLandingTemplateRequest;
use App\Http\Requests\Admin\LandingTemplates\UpdateLandingTemplateRequest;
use App\Models\Template;
use App\Services\Landing\LandingTemplateManagementService;
use App\Support\LandingTemplateVariables;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages system landing template creation and editing.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class LandingTemplateController extends Controller
{
    /**
     * Display the centrally managed landing template list.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View Rendered template list.
     *
     * @since 2.0.0
     */
    public function index(Request $request): View
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['updated_at', 'name', 'code', 'is_active'],
            'updated_at'
        );
        $query = Template::query()->withCount('themeSettings');

        SearchFilter::apply($query, $pagination->search, ['name', 'code', 'description']);

        if (in_array($request->string('status')->toString(), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        return view('admin.system.landing-templates.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'templates' => $query
                ->orderByDesc('is_default')
                ->orderBy($pagination->sort, $pagination->direction)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
                ->withQueryString(),
        ]);
    }

    /**
     * Display the landing template creation form.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View Rendered creation form.
     *
     * @since 2.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function create(Request $request): View
    {
        return view('admin.system.landing-templates.create', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'templateVariables' => LandingTemplateVariables::definitions(),
            'backLinkVariableExample' => LandingTemplateVariables::backLinkExample(),
            'templateDefaults' => $this->editableDefaults(),
        ]);
    }

    /**
     * Persist a new landing template.
     *
     * @param  StoreLandingTemplateRequest  $request  Validated template request.
     * @param  LandingTemplateManagementService  $templates  Template management service.
     * @return RedirectResponse Redirect to the template editor.
     *
     * @since 2.0.0
     */
    public function store(
        StoreLandingTemplateRequest $request,
        LandingTemplateManagementService $templates,
    ): RedirectResponse {
        $template = $templates->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.system.landing-templates.edit', $template)
            ->with('status', __('landing_templates.messages.created'));
    }

    /**
     * Display the landing template code editor.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Template  $landingTemplate  Template being edited.
     * @return View Rendered template editor.
     *
     * @since 2.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function edit(Request $request, Template $landingTemplate): View
    {
        return view('admin.system.landing-templates.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'landingTemplate' => $landingTemplate->loadCount('themeSettings'),
            'templateVariables' => LandingTemplateVariables::definitions(),
            'backLinkVariableExample' => LandingTemplateVariables::backLinkExample(),
            'templateDefaults' => $this->editableDefaults(),
        ]);
    }

    /**
     * Update a landing template and its revision.
     *
     * @param  UpdateLandingTemplateRequest  $request  Validated template request.
     * @param  Template  $landingTemplate  Template being updated.
     * @param  LandingTemplateManagementService  $templates  Template management service.
     * @return RedirectResponse Redirect back to the editor.
     *
     * @since 2.0.0
     */
    public function update(
        UpdateLandingTemplateRequest $request,
        Template $landingTemplate,
        LandingTemplateManagementService $templates,
    ): RedirectResponse {
        $templates->update($landingTemplate, $request->validated(), $request->user());

        return back()->with('status', __('landing_templates.messages.updated'));
    }

    /**
     * Resolve creation defaults from the active database-managed template.
     *
     * @return array{
     *     primary_color: string,
     *     consent_text: string,
     *     seo_title: string|null,
     *     seo_description: string|null,
     *     seo_image_path: string|null,
     *     seo_robots: string,
     *     tracking_head_code: string|null,
     *     tracking_body_code: string|null,
     *     header_html: string,
     *     body_html: string|null,
     *     footer_html: string,
     *     back_link_title: string|null,
     *     back_link_url: string|null
     * } Editable template defaults.
     *
     * @since 2.1.0
     */
    private function editableDefaults(): array
    {
        $template = Template::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        return [
            'primary_color' => (string) ($template?->primary_color ?: '#0f766e'),
            'consent_text' => (string) ($template?->consent_text ?: 'با پردازش تصویر برای این سرویس موافقم.'),
            'seo_title' => $template?->seo_title,
            'seo_description' => $template?->seo_description,
            'seo_image_path' => $template?->seo_image_path,
            'seo_robots' => (string) ($template?->seo_robots ?: 'index,follow'),
            'tracking_head_code' => $template?->tracking_head_code,
            'tracking_body_code' => $template?->tracking_body_code,
            'header_html' => (string) ($template?->header_html ?: '<div class="mx-auto w-full max-w-6xl px-4 py-5"><p class="text-xl font-bold">قالب جدید کلینیک</p></div>'),
            'body_html' => $template?->body_html,
            'footer_html' => (string) ($template?->footer_html ?: '<div class="mx-auto w-full max-w-6xl px-4 py-5 text-sm"><p>اطلاعات کلینیک</p></div>'),
            'back_link_title' => $template?->back_link_title,
            'back_link_url' => $template?->back_link_url,
        ];
    }
}
