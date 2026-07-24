<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\ClinicNotification;
use App\Models\Tenant;
use App\Rules\JalaliDateTime;
use App\Services\Notifications\ClinicNotificationService;
use App\Support\Html\SimpleRichTextSanitizer;
use App\Support\JalaliDate;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manages platform-created messages delivered to clinic dashboards.
 *
 * @since 1.0.0
 */
class SystemClinicNotificationController extends Controller
{
    /**
     * Display the system clinic notification listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('notifications.manage'), Response::HTTP_FORBIDDEN);

        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'visible_from', 'title', 'type', 'importance'],
            'created_at',
            'desc'
        );

        $query = ClinicNotification::query()
            ->with('creator')
            ->withCount([
                'recipients',
                'recipients as unread_recipients_count' => fn ($query) => $query->unread(),
            ]);

        SearchFilter::apply($query, $pagination->search, ['title', 'body']);

        $query
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('importance'), fn ($query) => $query->where('importance', $request->string('importance')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));

        $notifications = $query
            ->orderBy($pagination->sort, $pagination->direction)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();

        $now = JalaliDate::nowDateTimeString();
        $tenants = Tenant::query()
            ->where('status', 'active')
            ->with([
                'subscriptions' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('ends_at', '>', $now),
                'serviceEntitlements' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
                'aiModelAccesses' => fn ($query) => $query
                    ->where('ai_model_accesses.is_active', true)
                    ->where('clinic_ai_model_access.is_active', true),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.system.notifications.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'notifications' => $notifications,
            'tenants' => $tenants,
            'categories' => AiModelCategory::query()->where('status', 'active')->orderBy('sort_order')->orderBy('name')->get(),
            'aiModelAccesses' => AiModelAccess::query()->active()->orderBy('name')->get(),
            'types' => $this->types(),
            'importances' => $this->importances(),
        ]);
    }

    /**
     * Persist a new system clinic notification record.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  ClinicNotificationService  $notifications  The notifications value.
     * @param  SimpleRichTextSanitizer  $sanitizer  The sanitizer value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function store(
        Request $request,
        ClinicNotificationService $notifications,
        SimpleRichTextSanitizer $sanitizer
    ): RedirectResponse {
        abort_unless($request->user()?->hasPermission('notifications.manage'), Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'body_html' => ['nullable', 'string', 'max:30000'],
            'type' => ['required', Rule::in(array_keys($this->types()))],
            'importance' => ['required', Rule::in(array_keys($this->importances()))],
            'target_scope' => ['required', Rule::in(['all', 'tenant', 'selected'])],
            'target_tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')->where('status', 'active')],
            'target_tenant_ids' => ['nullable', 'array'],
            'target_tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')->where('status', 'active')],
            'filter_plan_state' => ['nullable', Rule::in(['any', 'active', 'inactive'])],
            'filter_ai_model_category_id' => ['nullable', 'integer', Rule::exists('ai_model_categories', 'id')],
            'filter_ai_model_access_id' => ['nullable', 'integer', Rule::exists('ai_model_accesses', 'id')],
            'send_timing' => ['nullable', Rule::in(['immediate', 'scheduled'])],
            'visible_from' => ['nullable', new JalaliDateTime],
            'visible_until' => ['nullable', new JalaliDateTime],
        ]);

        if ($request->filled('target_tenant_id') && empty($data['target_tenant_ids'])) {
            $data['target_tenant_ids'] = [(int) $request->input('target_tenant_id')];
        }

        if ($request->boolean('filter_active_plan') && ($data['filter_plan_state'] ?? 'any') === 'any') {
            $data['filter_plan_state'] = 'active';
        }

        $rawBodyHtml = $request->filled('body_html')
            ? (string) $request->input('body_html')
            : e((string) $request->input('body'));

        $data['body_html'] = $sanitizer->sanitize($rawBodyHtml);
        $data['body'] = $sanitizer->plainText($data['body_html']);

        if ($data['body'] === '') {
            throw ValidationException::withMessages([
                'body_html' => __('notifications.errors.empty_body'),
            ]);
        }

        if ($data['target_scope'] === 'tenant' && count((array) $request->input('target_tenant_ids', [])) !== 1) {
            throw ValidationException::withMessages([
                'target_tenant_ids' => __('notifications.errors.single_tenant_required'),
            ]);
        }

        if ($data['target_scope'] === 'selected' && count((array) $request->input('target_tenant_ids', [])) === 0) {
            throw ValidationException::withMessages([
                'target_tenant_ids' => __('notifications.errors.selected_tenants_required'),
            ]);
        }

        $isImmediate = $request->input('send_timing', 'immediate') === 'immediate';

        if (! $isImmediate && ! $request->filled('visible_from')) {
            throw ValidationException::withMessages([
                'visible_from' => __('notifications.errors.visible_from_required'),
            ]);
        }

        $data['visible_from'] = ! $isImmediate && $request->filled('visible_from')
            ? JalaliDate::fromJalaliDateTime((string) $request->input('visible_from'))
            : null;
        $data['visible_until'] = ! $isImmediate && $request->filled('visible_until')
            ? JalaliDate::fromJalaliDateTime((string) $request->input('visible_until'))
            : null;

        if ($data['visible_from'] && $data['visible_until'] && $data['visible_until']->lessThan($data['visible_from'])) {
            throw ValidationException::withMessages([
                'visible_until' => __('notifications.errors.visible_until_before_from'),
            ]);
        }

        $recipientCount = $notifications->resolveTenants($notifications->targetingRules($data))->count();

        if ($recipientCount === 0) {
            throw ValidationException::withMessages([
                'target_scope' => __('notifications.errors.no_recipients'),
            ]);
        }

        $notifications->createAndDeliver($data, $request->user());

        return back()->with('status', __('notifications.messages.created', ['count' => number_format($recipientCount)]));
    }

    /**
     * Display the requested system clinic notification resource.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  ClinicNotification  $notification  The notification value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function show(Request $request, ClinicNotification $notification): View
    {
        abort_unless($request->user()?->hasPermission('notifications.manage'), Response::HTTP_FORBIDDEN);

        $notification->load([
            'creator',
            'recipients' => fn ($query) => $query->with('tenant')->orderBy('read_at')->orderBy('id'),
        ]);

        return view('admin.system.notifications.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'notification' => $notification,
            'types' => $this->types(),
            'importances' => $this->importances(),
        ]);
    }

    /**
     * Execute the types operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    private function types(): array
    {
        return __('notifications.types');
    }

    /**
     * Execute the importances operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    private function importances(): array
    {
        return __('notifications.importances');
    }
}
