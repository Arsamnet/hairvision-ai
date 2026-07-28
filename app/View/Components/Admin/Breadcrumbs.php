<?php

namespace App\View\Components\Admin;

use App\Models\Tenant;
use App\Support\AdminNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * Builds the reusable dashboard breadcrumb trail from the active admin route.
 *
 * @since 1.0.0
 */
class Breadcrumbs extends Component
{
    public readonly string $resolvedPageTitle;

    /**
     * Create a new breadcrumbs instance.
     *
     * @param  string|null  $pageTitle  The page title value.
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     *
     * @since 1.0.0
     */
    public function __construct(
        public readonly ?string $pageTitle = null,
        public readonly ?Tenant $tenant = null,
    ) {
        $this->resolvedPageTitle = trim((string) ($pageTitle ?: __('dashboard.dashboard')));
    }

    /**
     * Execute the render operation.
     *
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function render(): View
    {
        return view('components.admin.breadcrumbs', [
            'crumbs' => $this->crumbs(),
        ]);
    }

    /**
     * Execute the crumbs operation.
     *
     * @return array<int, array{label: string, url: ?string, icon: ?string}>
     *
     * @since 1.0.0
     */
    private function crumbs(): array
    {
        $crumbs = [];

        $this->addCrumb($crumbs, __('dashboard.dashboard'), $this->dashboardUrl(), 'layout-dashboard');

        if (request()->routeIs('admin.dashboard')) {
            $crumbs[0]['url'] = null;

            return $crumbs;
        }

        $context = AdminNavigation::context();
        $group = $context['group'];
        $item = $context['item'];

        if (! $group || ! $item) {
            $this->addCrumb($crumbs, $this->resolvedPageTitle);

            return $crumbs;
        }

        if ($group['key'] !== 'dashboard') {
            $this->addCrumb($crumbs, $group['label'], icon: $group['icon']);
        }

        if (request()->routeIs($item['route'])) {
            $this->addCrumb($crumbs, $item['label'], icon: $item['icon']);

            return $crumbs;
        }

        $this->addCrumb($crumbs, $item['label'], $this->routeUrl($item['route']), $item['icon']);
        $this->addCrumb($crumbs, $this->resolvedPageTitle, icon: $item['icon']);

        return $crumbs;
    }

    /**
     * Execute the dashboard url operation.
     *
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function dashboardUrl(): ?string
    {
        return $this->routeUrl('admin.dashboard');
    }

    /**
     * Execute the add crumb operation.
     *
     * @param  array<int, array{label: string, url: ?string, icon: ?string}>  $crumbs
     * @param  string  $label  The label value.
     * @param  string|null  $url  The url value.
     * @param  string|null  $icon  The icon value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function addCrumb(array &$crumbs, string $label, ?string $url = null, ?string $icon = null): void
    {
        if (blank($label)) {
            return;
        }

        $crumbs[] = [
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
        ];
    }

    /**
     * Execute the route url operation.
     *
     * @param  array<string, mixed>  $parameters
     * @param  string  $name  The name value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function routeUrl(string $name, array $parameters = []): ?string
    {
        if (! Route::has($name)) {
            return null;
        }

        if (collect($parameters)->contains(fn (mixed $value): bool => blank($value))) {
            return null;
        }

        return route($name, $parameters);
    }
}
