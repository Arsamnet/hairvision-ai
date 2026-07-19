<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\CreditTransaction;
use App\Models\GenerationRequest;
use App\Models\PlanServiceEntitlement;
use App\Models\SubscriptionServiceEntitlement;
use App\Services\AI\ExecutableAiModelSyncService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manages system-owned AI model categories and requestable outputs.
 *
 * @since 2.0.0
 */
class AiModelCategoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.view'), Response::HTTP_FORBIDDEN);

        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'code', 'name', 'status', 'sort_order'],
            'sort_order',
            'asc'
        );

        $query = AiModelCategory::query()
            ->with(['outputs' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->withCount(['outputs', 'models']);

        SearchFilter::apply($query, $pagination->search, ['code', 'name', 'description']);

        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));

        $categories = $query
            ->orderBy($pagination->sort, $pagination->direction)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();

        $this->attachOperationalCounts($categories);

        return view('admin.system.ai-model-categories.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.create'), Response::HTTP_FORBIDDEN);

        $data = $this->validatedCategoryData($request, $this->categoryRules());

        AiModelCategory::query()->create($data + [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('status', __('ai_models.categories.messages.created'));
    }

    public function show(Request $request, AiModelCategory $category): View
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.view'), Response::HTTP_FORBIDDEN);

        $category->load(['outputs' => fn ($query) => $query
            ->withCount(['modelOutputConfigs', 'generationRequests'])
            ->orderBy('sort_order')
            ->orderBy('id'),
        ])
            ->loadCount(['outputs', 'models']);

        $usage = $this->categoryUsage($category);
        $category->setAttribute('legacy_model_accesses_count', $usage['legacy_model_accesses']);
        $category->setAttribute('total_models_count', $usage['models'] + $usage['legacy_model_accesses']);
        $category->setAttribute('usage_references_count', array_sum($usage));

        return view('admin.system.ai-model-categories.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'category' => $category,
            'usage' => $usage,
        ]);
    }

    public function update(Request $request, AiModelCategory $category): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.update'), Response::HTTP_FORBIDDEN);

        $data = $this->validatedCategoryData($request, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'card_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ]);

        $category->update($data + ['updated_by' => $request->user()?->id]);

        return back()->with('status', __('ai_models.categories.messages.updated'));
    }

    public function toggle(Request $request, AiModelCategory $category): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.update'), Response::HTTP_FORBIDDEN);

        if ($category->status === 'archived') {
            return back()->withErrors([
                'category' => __('ai_models.categories.errors.toggle_archived'),
            ]);
        }

        $category->forceFill([
            'status' => $category->status === 'active' ? 'inactive' : 'active',
            'updated_by' => $request->user()?->id,
        ])->save();

        return back()->with('status', __('ai_models.categories.messages.toggled'));
    }

    public function destroy(Request $request, AiModelCategory $category): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.delete'), Response::HTTP_FORBIDDEN);

        if ($this->categoryIsUsed($category)) {
            return back()->withErrors([
                'category' => __('ai_models.categories.errors.delete_conflict'),
            ]);
        }

        DB::transaction(function () use ($category): void {
            $category->outputs()->delete();
            $category->delete();
        });

        return back()->with('status', __('ai_models.categories.messages.deleted'));
    }

    public function archive(Request $request, AiModelCategory $category): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_categories.update'), Response::HTTP_FORBIDDEN);

        if (! $this->categoryIsUsed($category)) {
            return back()->withErrors([
                'category' => __('ai_models.categories.errors.archive_unused'),
            ]);
        }

        $category->update([
            'status' => 'archived',
            'updated_by' => $request->user()?->id,
        ]);

        return back()->with('status', __('ai_models.categories.messages.archived'));
    }

    public function storeOutput(
        Request $request,
        AiModelCategory $category,
        ExecutableAiModelSyncService $executableModels
    ): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_category_outputs.manage'), Response::HTTP_FORBIDDEN);

        $data = $request->validate($this->outputRules($category));

        $category->outputs()->create($data);
        $executableModels->syncForCategory($category);

        return back()->with('status', __('ai_models.outputs.messages.created'));
    }

    public function updateOutput(
        Request $request,
        AiModelCategory $category,
        AiModelCategoryOutput $output,
        ExecutableAiModelSyncService $executableModels
    ): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_category_outputs.manage'), Response::HTTP_FORBIDDEN);
        abort_unless((int) $output->ai_model_category_id === (int) $category->id, Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prompt' => ['required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'credit_cost' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $output->update($data + ['is_active' => $request->boolean('is_active')]);
        $executableModels->syncForCategory($category);

        return back()->with('status', __('ai_models.outputs.messages.updated'));
    }

    public function destroyOutput(Request $request, AiModelCategory $category, AiModelCategoryOutput $output): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('ai_model_category_outputs.manage'), Response::HTTP_FORBIDDEN);
        abort_unless((int) $output->ai_model_category_id === (int) $category->id, Response::HTTP_NOT_FOUND);

        if (
            GenerationRequest::query()->where('ai_model_category_output_id', $output->id)->exists()
            || $output->modelOutputConfigs()->exists()
        ) {
            return back()->withErrors([
                'output' => __('ai_models.outputs.errors.delete_conflict'),
            ]);
        }

        $output->delete();

        return back()->with('status', __('ai_models.outputs.messages.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRules(?AiModelCategory $category = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('ai_model_categories', 'code')->ignore($category),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'card_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function validatedCategoryData(Request $request, array $rules): array
    {
        $data = $request->validate($rules);
        unset($data['card_image']);

        if ($request->hasFile('card_image')) {
            $file = $request->file('card_image');
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->storeAs(
                'category-cards',
                Str::uuid().'.'.$extension,
                'public'
            );

            $data['card_image_path'] = '/storage/'.$path;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function outputRules(AiModelCategory $category): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('ai_model_category_outputs', 'code')->where('ai_model_category_id', $category->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prompt' => ['required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'credit_cost' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function attachOperationalCounts(LengthAwarePaginator $categories): void
    {
        $categories->getCollection()->transform(function (AiModelCategory $category): AiModelCategory {
            $usage = $this->categoryUsage($category);

            $category->setAttribute('legacy_model_accesses_count', $usage['legacy_model_accesses']);
            $category->setAttribute('total_models_count', $usage['models'] + $usage['legacy_model_accesses']);
            $category->setAttribute('usage_references_count', array_sum($usage));
            $category->setAttribute('can_delete_category', array_sum($usage) === 0);
            $category->setAttribute('can_archive_category', array_sum($usage) > 0 && $category->status !== 'archived');

            return $category;
        });
    }

    /**
     * @return array{models: int, legacy_model_accesses: int, plan_entitlements: int, subscription_entitlements: int, credit_transactions: int, generation_requests: int}
     */
    private function categoryUsage(AiModelCategory $category): array
    {
        return [
            'models' => $category->models()->count(),
            'legacy_model_accesses' => AiModelAccess::query()
                ->where('usage_category_key', $category->code)
                ->count(),
            'plan_entitlements' => PlanServiceEntitlement::query()
                ->where('service_key', $category->code)
                ->count(),
            'subscription_entitlements' => SubscriptionServiceEntitlement::query()
                ->where('service_key', $category->code)
                ->count(),
            'credit_transactions' => CreditTransaction::query()
                ->where('service_key', $category->code)
                ->count(),
            'generation_requests' => GenerationRequest::query()
                ->where('ai_model_category_id', $category->id)
                ->count(),
        ];
    }

    private function categoryIsUsed(AiModelCategory $category): bool
    {
        return array_sum($this->categoryUsage($category)) > 0;
    }
}
