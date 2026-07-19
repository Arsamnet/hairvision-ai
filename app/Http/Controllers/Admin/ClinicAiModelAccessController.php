<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Ai\AssignAiModelToClinicAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiModels\StoreClinicAiModelAccessRequest;
use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Services\AI\AiModelResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manages clinic assignments for AI model access records.
 *
 * @since 1.0.0
 */
class ClinicAiModelAccessController extends Controller
{
    public function index(Request $request, Tenant $clinic, AiModelResolver $resolver): View
    {
        Gate::authorize('assignToClinic', AiModelAccess::class);
        $this->authorizeClinicVisibility($request, $clinic);

        return view('admin.clinics.ai-models.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinic' => $clinic,
            'models' => $resolver->models(),
            'usageCategories' => $resolver->usageCategories(),
            'resolver' => $resolver,
            'assignedAccesses' => $clinic->aiModelAccesses()
                ->withPivot(['is_active', 'assigned_by'])
                ->orderBy('usage_category_key')
                ->orderBy('name')
                ->get(),
            'availableAccesses' => AiModelAccess::query()
                ->active()
                ->orderBy('usage_category_key')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(
        StoreClinicAiModelAccessRequest $request,
        Tenant $clinic,
        AssignAiModelToClinicAction $assign
    ): RedirectResponse {
        $this->authorizeClinicVisibility($request, $clinic);

        $access = AiModelAccess::query()->findOrFail($request->integer('ai_model_access_id'));
        $assign->execute($clinic, $access, $request->user(), $request->boolean('is_active', true));

        return back()->with('status', __('ai_models.messages.assigned'));
    }

    public function update(
        StoreClinicAiModelAccessRequest $request,
        Tenant $clinic,
        AiModelAccess $aiModelAccess,
        AssignAiModelToClinicAction $assign
    ): RedirectResponse {
        $this->authorizeClinicVisibility($request, $clinic);

        $assign->execute($clinic, $aiModelAccess, $request->user(), $request->boolean('is_active'));

        return back()->with('status', __('ai_models.messages.assignment_updated'));
    }

    public function destroy(Request $request, Tenant $clinic, AiModelAccess $aiModelAccess): RedirectResponse
    {
        Gate::authorize('assignToClinic', AiModelAccess::class);
        $this->authorizeClinicVisibility($request, $clinic);

        $clinic->aiModelAccesses()->detach($aiModelAccess->id);

        return back()->with('status', __('ai_models.messages.unassigned'));
    }

    private function authorizeClinicVisibility(Request $request, Tenant $clinic): void
    {
        abort_unless($request->user()?->canAccessTenant($clinic->id), 403, __('permissions.tenant_denied'));
    }
}
