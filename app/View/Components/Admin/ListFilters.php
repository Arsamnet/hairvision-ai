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
     * @param  array<int, array<string, mixed>>  $filters
     * @param  array<string, string>  $sorts
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
    ) {}

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
     * @return Collection<int, UserSavedFilter>
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
     * @return array<string, string>
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
     * @return array<int, string>
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
