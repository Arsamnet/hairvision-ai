<?php

namespace App\View\Components\Admin;

use App\Models\UserSavedFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * Shared dashboard list filtering surface with saved per-user presets.
 *
 * @since 1.0.0
 */
class ListFilters extends Component
{
    /**
     * Create a new list filters instance.
     *
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<string, string>  $sorts
     * @param  string|null  $action  The action value.
     * @param  string|null  $presetKey  The preset key value.
     * @param  string|null  $searchPlaceholder  The search placeholder value.
     * @param  string  $searchName  The search name value.
     * @param  bool  $canSearch  The can search value.
     * @param  bool  $showDates  The show dates value.
     * @param  bool  $showSort  The show sort value.
     * @param  bool  $showPerPage  The show per page value.
     * @param  int  $primaryFilterCount  The primary filter count value.
     *
     * @since 1.0.0
     */
    public function __construct(
        public readonly ?string $action = null,
        public readonly ?string $presetKey = null,
        public readonly ?string $searchPlaceholder = null,
        public readonly string $searchName = 'search',
        public readonly bool $canSearch = true,
        public readonly array $filters = [],
        public readonly array $sorts = [],
        public readonly bool $showDates = true,
        public readonly bool $showSort = true,
        public readonly bool $showPerPage = true,
        public readonly int $primaryFilterCount = 2,
    ) {}

    /**
     * Execute the render operation.
     *
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view('components.admin.list-filters', [
            'filterAction' => $this->action ?: request()->url(),
            'resolvedPresetKey' => $this->presetKey ?: (request()->route()?->getName() ?? request()->path()),
            'savedFilters' => $this->savedFilters(),
            'activeParameters' => $this->activeParameters(),
            'panelOpen' => $this->panelOpen(),
        ]);
    }

    /**
     * Persist d filters.
     *
     * @return Collection<int, UserSavedFilter>
     *
     * @since 1.0.0
     */
    private function savedFilters(): Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return UserSavedFilter::query()
            ->where('user_id', $user->id)
            ->where('filter_key', $this->presetKey ?: (request()->route()?->getName() ?? request()->path()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Execute the active parameters operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    private function activeParameters(): array
    {
        $parameters = [];

        foreach ($this->parameterNames() as $name) {
            $value = request()->input($name);

            if ($name === $this->searchName && ! filled($value)) {
                $value = request()->input('q');
            }

            if (! is_scalar($value) || ! filled($value)) {
                continue;
            }

            $parameters[$name] = (string) $value;
        }

        return $parameters;
    }

    /**
     * Execute the panel open operation.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function panelOpen(): bool
    {
        foreach ($this->parameterNames(includeSearch: false) as $name) {
            if (filled(request()->input($name))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute the parameter names operation.
     *
     * @param  bool  $includeSearch  The include search value.
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function parameterNames(bool $includeSearch = true): array
    {
        $names = collect($this->filters)
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();

        if ($includeSearch) {
            array_unshift($names, $this->searchName);
        }

        if ($this->showDates) {
            $names[] = 'created_from';
            $names[] = 'created_until';
        }

        if ($this->showSort) {
            $names[] = 'sort';
            $names[] = 'direction';
        }

        if ($this->showPerPage) {
            $names[] = 'per_page';
        }

        return array_values(array_unique($names));
    }
}
