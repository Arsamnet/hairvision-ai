<?php

namespace App\Providers;

use App\Models\Lead;
use App\Models\SmsProviderAccount;
use App\Policies\LeadPolicy;
use App\Policies\SmsProviderAccountPolicy;
use App\Contracts\Sms\SmsProviderFactoryInterface;
use App\Services\Otp\SmsOtpSender;
use App\Services\Otp\OtpSenderInterface;
use App\Services\Sms\SmsProviderFactory;
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
        $this->app->bind(OtpSenderInterface::class, SmsOtpSender::class);
        $this->app->bind(SmsProviderFactoryInterface::class, SmsProviderFactory::class);
    }

    public function boot(): void
    {
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(SmsProviderAccount::class, SmsProviderAccountPolicy::class);
    }
}
