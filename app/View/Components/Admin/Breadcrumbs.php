<?php

namespace App\View\Components\Admin;

use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    public function __construct(
        public readonly ?string $pageTitle = null,
        public readonly ?Tenant $tenant = null,
    ) {
        $this->resolvedPageTitle = trim((string) ($pageTitle ?: __('dashboard.dashboard')));
    }

    public function render(): View
    {
        return view('components.admin.breadcrumbs', [
            'crumbs' => $this->crumbs(),
        ]);
    }

    /**
     * @return array<int, array{label: string, url: ?string, icon: ?string}>
     */
    private function crumbs(): array
    {
        $crumbs = [];

        $this->addCrumb($crumbs, __('dashboard.dashboard'), $this->dashboardUrl(), 'layout-dashboard');

        if (request()->routeIs('admin.dashboard')) {
            $crumbs[0]['url'] = null;

            return $crumbs;
        }

        $section = $this->sectionDefinitions()
            ->first(fn (array $definition): bool => $this->matchesAny($definition['patterns']));

        if (! $section) {
            $this->addCrumb($crumbs, $this->resolvedPageTitle);

            return $crumbs;
        }

        $sectionUrl = $this->routeUrl($section['route'], ($section['parameters'])());

        if (request()->routeIs($section['route']) || $this->resolvedPageTitle === $section['label']) {
            $this->addCrumb($crumbs, $section['label'], null, $section['icon']);

            return $crumbs;
        }

        $this->addCrumb($crumbs, $section['label'], $sectionUrl, $section['icon']);
        $this->addCrumb($crumbs, $this->resolvedPageTitle);

        return $crumbs;
    }

    private function dashboardUrl(): ?string
    {
        return $this->routeUrl('admin.dashboard');
    }

    /**
     * @param  array<int, array{label: string, url: ?string, icon: ?string}>  $crumbs
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
     * @return Collection<int, array{
     *     patterns: array<int, string>,
     *     label: string,
     *     route: string,
     *     parameters: callable(): array<string, mixed>,
     *     icon: string
     * }>
     */
    private function sectionDefinitions(): Collection
    {
        return collect([
            $this->section(['admin.clinics.ai-models.*'], __('ai_models.assignment_title'), 'admin.clinics.show', fn (): array => [
                'clinic' => request()->route('clinic'),
            ], 'sparkles'),
            $this->section(['admin.clinics.*'], __('clinics.title'), 'admin.clinics.index', icon: 'building'),
            $this->section(['admin.leads.*'], __('leads.title'), 'admin.leads.index', icon: 'contact'),
            $this->section(['admin.users.*'], __('system.users_title'), 'admin.users.index', icon: 'users'),
            $this->section(['admin.system.ai-model-categories.*'], __('ai_models.categories.title'), 'admin.system.ai-model-categories.index', icon: 'category'),
            $this->section(['admin.ai-models.*'], __('ai_models.title'), 'admin.ai-models.index', icon: 'sparkles'),
            $this->section(['admin.plans.*'], __('plans.title'), 'admin.plans.index', icon: 'package'),
            $this->section(['admin.billing.*'], __('payments.title'), 'admin.billing.index', icon: 'invoice'),
            $this->section(['admin.reports', 'admin.reports.*'], __('reports.title'), 'admin.reports', icon: 'chart'),
            $this->section(['admin.system.roles.*'], __('system.roles_title'), 'admin.system.roles.index', icon: 'shield'),
            $this->section(['admin.system.permissions.*'], __('system.permissions_title'), 'admin.system.permissions.index', icon: 'shield'),
            $this->section(['admin.system.settings.*'], __('system.settings_title'), 'admin.system.settings.edit', icon: 'settings'),
            $this->section(['admin.system.sms-gateways.*'], __('system.sms_gateways_title'), 'admin.system.sms-gateways.index', icon: 'message'),
            $this->section(['admin.system.payment-gateways.*'], __('system.payment_gateways_title'), 'admin.system.payment-gateways.index', icon: 'credit-card'),
            $this->section(['admin.system.reports.*'], __('reports.management_title'), 'admin.system.reports.index', icon: 'chart'),
        ]);
    }

    /**
     * @param  array<int, string>  $patterns
     * @return array{
     *     patterns: array<int, string>,
     *     label: string,
     *     route: string,
     *     parameters: callable(): array<string, mixed>,
     *     icon: string
     * }
     */
    private function section(
        array $patterns,
        string $label,
        string $route,
        ?callable $parameters = null,
        string $icon = 'layout-dashboard',
    ): array {
        return [
            'patterns' => $patterns,
            'label' => $label,
            'route' => $route,
            'parameters' => $parameters ?: fn (): array => [],
            'icon' => $icon,
        ];
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesAny(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parameters
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
