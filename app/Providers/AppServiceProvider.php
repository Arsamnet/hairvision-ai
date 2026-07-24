<?php

namespace App\Providers;

use App\Models\AiModelAccess;
use App\Models\Lead;
use App\Models\SmsGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AiModelAccessPolicy;
use App\Policies\LeadPolicy;
use App\Policies\SmsGatewayPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
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
    /**
     * Execute the register operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->bind(OtpSenderInterface::class, LocalOtpSender::class);
    }

    /**
     * Register model lifecycle behavior.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(AiModelAccess::class, AiModelAccessPolicy::class);
        Gate::policy(SmsGateway::class, SmsGatewayPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define('permission', function ($user, string $permission, ?int $tenantId = null): bool {
            return app(PermissionService::class)->can($user, $permission, $tenantId);
        });
    }
}
