<?php

namespace App\Providers;

use App\Models\Lead;
use App\Policies\LeadPolicy;
use App\Services\Auth\PermissionService;
use App\Services\Otp\LocalOtpSender;
use App\Services\Otp\OtpSenderInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Application service provider reserved for project-specific bindings.
 *
 * @since 1.0.0
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpSenderInterface::class, LocalOtpSender::class);
    }

    public function boot(): void
    {
        Gate::policy(Lead::class, LeadPolicy::class);

        Gate::define('permission', function ($user, string $permission, ?int $tenantId = null): bool {
            return app(PermissionService::class)->can($user, $permission, $tenantId);
        });
    }
}
