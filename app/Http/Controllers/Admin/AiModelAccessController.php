<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiModels\StoreAiModelAccessRequest;
use App\Http\Requests\Admin\AiModels\UpdateAiModelAccessRequest;
use App\Models\AiModelAccess;
use App\Models\AiModelUsageLog;
use App\Models\Tenant;
use App\Services\AI\AiModelManager;
use App\Services\AI\AiModelResolver;
use App\Support\Pagination\PaginatedResponse;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manages global AI model access records for system administrators.
 *
 * @since 1.0.0
 */
class AiModelAccessController extends Controller
{
    public function index(Request $request, AiModelManager $manager, AiModelResolver $resolver): View|JsonResponse
    {
        Gate::authorize('viewAny', AiModelAccess::class);

        if ($request->expectsJson()) {
            $pagination = PaginationParameters::fromRequest(
                $request,
                ['created_at', 'name', 'model_key', 'usage_category_key', 'credit_used', 'usage_count', 'priority', 'last_used_at'],
                'created_at'
            );

            if ($pagination->search && ! $request->user()->hasPermission('ai_models.search', $request->attributes->get('adminTenant')?->id)) {
                abort(403, __('permissions.denied'));
            }

            $paginator = $manager->query($request, $pagination)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page);

            return response()->json(PaginatedResponse::make($paginator));
        }

        return view('admin.ai-models.index', $this->formData($request, $resolver) + [
            'aiModelAccesses' => $manager->paginate($request),
        ]);
    }

    public function show(Request $request, AiModelAccess $aiModelAccess, AiModelResolver $resolver): View
    {
        Gate::authorize('view', $aiModelAccess);

        return view('admin.ai-models.show', $this->formData($request, $resolver) + [
            'aiModelAccess' => $aiModelAccess
                ->load(['clinics', 'creator', 'updater'])
                ->loadCount(['usageLogs', 'usageLogs as completed_usage_logs_count' => fn ($query) => $query->where('status', 'completed')]),
            'usageLogs' => AiModelUsageLog::query()
                ->where('ai_model_access_id', $aiModelAccess->id)
                ->with(['clinic', 'user'])
                ->latest('id')
                ->paginate(10, ['*'], 'logs_page')
                ->withQueryString(),
        ]);
    }

    public function edit(Request $request, AiModelAccess $aiModelAccess, AiModelResolver $resolver): View
    {
        Gate::authorize('update', $aiModelAccess);

        return view('admin.ai-models.edit', $this->formData($request, $resolver) + [
            'aiModelAccess' => $aiModelAccess
                ->load(['clinics'])
                ->loadCount(['usageLogs', 'usageLogs as completed_usage_logs_count' => fn ($query) => $query->where('status', 'completed')]),
        ]);
    }

    public function store(StoreAiModelAccessRequest $request, AiModelManager $manager): RedirectResponse
    {
        $access = $manager->create($request->validated(), $request->user());

        if ($request->boolean('return_to_index')) {
            return redirect()->route('admin.ai-models.index')->with('status', __('ai_models.messages.created'));
        }

        return redirect()->route('admin.ai-models.show', $access)->with('status', __('ai_models.messages.created'));
    }

    public function update(UpdateAiModelAccessRequest $request, AiModelAccess $aiModelAccess, AiModelManager $manager): RedirectResponse
    {
        $manager->update($aiModelAccess, $request->validated(), $request->user());

        return redirect()->route('admin.ai-models.show', $aiModelAccess)->with('status', __('ai_models.messages.updated'));
    }

    public function destroy(AiModelAccess $aiModelAccess, AiModelManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $aiModelAccess);

        $manager->delete($aiModelAccess);

        return redirect()->route('admin.ai-models.index')->with('status', __('ai_models.messages.deleted'));
    }

    public function toggle(Request $request, AiModelAccess $aiModelAccess, AiModelManager $manager): RedirectResponse
    {
        Gate::authorize('toggle', $aiModelAccess);

        $manager->toggle($aiModelAccess, $request->user());

        return back()->with('status', __('ai_models.messages.toggled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, AiModelResolver $resolver): array
    {
        return [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'models' => $resolver->models(),
            'creatableModels' => $resolver->models(),
            'usageCategories' => $resolver->usageCategories(),
            'resolver' => $resolver,
            'clinics' => Tenant::query()->where('status', 'active')->orderBy('name')->get(),
        ];
    }
}
