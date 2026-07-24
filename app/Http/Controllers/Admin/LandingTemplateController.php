<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LandingTemplates\AssignLandingTemplateRequest;
use App\Http\Requests\Admin\LandingTemplates\StoreLandingTemplateRequest;
use App\Http\Requests\Admin\LandingTemplates\UpdateLandingTemplateRequest;
use App\Models\Template;
use App\Models\Tenant;
use App\Services\Landing\LandingTemplateManagementService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\SearchFilter;
use App\Support\StateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages system landing templates and clinic assignments.
 *
 * @since 2.0.0
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
     */
    public function create(Request $request): View
    {
        return view('admin.system.landing-templates.create', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
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
     */
    public function edit(Request $request, Template $landingTemplate): View
    {
        return view('admin.system.landing-templates.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'landingTemplate' => $landingTemplate->loadCount('themeSettings'),
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
     * Mark an active template as the platform default.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Template  $landingTemplate  Template selected as default.
     * @param  LandingTemplateManagementService  $templates  Template management service.
     * @return RedirectResponse Redirect back to the template list.
     *
     * @since 2.0.0
     */
    public function makeDefault(
        Request $request,
        Template $landingTemplate,
        LandingTemplateManagementService $templates,
    ): RedirectResponse {
        $templates->makeDefault($landingTemplate, $request->user());

        return back()->with('status', __('landing_templates.messages.default_updated'));
    }

    /**
     * Delete an unused non-default template.
     *
     * @param  Template  $landingTemplate  Template being deleted.
     * @param  LandingTemplateManagementService  $templates  Template management service.
     * @return RedirectResponse Redirect back to the template list.
     *
     * @since 2.0.0
     */
    public function destroy(
        Template $landingTemplate,
        LandingTemplateManagementService $templates,
    ): RedirectResponse {
        $templates->delete($landingTemplate);

        return back()->with('status', __('landing_templates.messages.deleted'));
    }

    /**
     * Display the clinic-to-template assignment section.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View Rendered clinic assignment list.
     *
     * @since 2.0.0
     */
    public function clinics(Request $request): View
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'slug', 'status'],
            'name'
        );
        $query = Tenant::query()->with('theme.template');

        SearchFilter::apply($query, $pagination->search, ['name', 'slug'], ['domains' => ['domain']]);

        $status = $request->string('status')->toString();

        if (in_array($status, StateCatalog::values('tenants.statuses'), true)) {
            $query->where('status', $status);
        }

        return view('admin.system.landing-templates.clinics', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinics' => $query
                ->orderBy($pagination->sort, $pagination->direction)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
                ->withQueryString(),
            'landingTemplates' => Template::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'defaultTemplate' => Template::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first(),
        ]);
    }

    /**
     * Assign an active landing template to a clinic.
     *
     * @param  AssignLandingTemplateRequest  $request  Validated assignment request.
     * @param  Tenant  $clinic  Clinic receiving the template.
     * @param  LandingTemplateManagementService  $templates  Template management service.
     * @return RedirectResponse Redirect back to clinic assignments.
     *
     * @since 2.0.0
     */
    public function assign(
        AssignLandingTemplateRequest $request,
        Tenant $clinic,
        LandingTemplateManagementService $templates,
    ): RedirectResponse {
        $template = Template::query()->findOrFail((int) $request->validated('template_id'));
        $templates->assignToTenant($clinic, $template);

        return back()->with('status', __('landing_templates.messages.assigned', [
            'template' => $template->name,
            'clinic' => $clinic->name,
        ]));
    }
}
