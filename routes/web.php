<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\SmsDeliveryLogController;
use App\Http\Controllers\Admin\SmsProviderAccountController;
use App\Http\Controllers\Admin\TenantSwitchController;
use App\Http\Controllers\Admin\UserSmsSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\EnsureAdminTenantSelected;
use App\Services\LandingPageVisitService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request, LandingPageVisitService $visitService, TenantService $tenants) {
    $host = strtolower($request->getHost());
    $domain = $tenants->resolveDomainForHost($host, false);

    if (
        $host === config('hairvision.primary_domain')
        || $domain?->type === 'platform'
        || (app()->environment('local') && $tenants->isLocalDevelopmentHost($host))
    ) {
        return view('public.landing');
    }

    abort_unless($domain && $domain->tenant && $domain->tenant->status === 'active', Response::HTTP_NOT_FOUND);

    $tenant = $domain->tenant;
    $tenants->bindCurrent($request, $tenant);
    $visitService->record($request, $tenant);

    return view('landing', ['tenant' => $tenant, 'theme' => $tenant->theme]);
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->name('otp.request');
    Route::get('/otp', [AuthController::class, 'showVerify'])->name('otp.form');
    Route::post('/otp/verify', [AuthController::class, 'verify'])->name('otp.verify');

    Route::middleware(['auth', EnsureAdminTenantSelected::class])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/tenant', TenantSwitchController::class)->name('tenant.switch');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
        Route::get('/sms/providers', [SmsProviderAccountController::class, 'index'])->name('sms.providers.index');
        Route::get('/sms/providers/create', [SmsProviderAccountController::class, 'create'])->name('sms.providers.create');
        Route::post('/sms/providers', [SmsProviderAccountController::class, 'store'])->name('sms.providers.store');
        Route::get('/sms/providers/{provider}/edit', [SmsProviderAccountController::class, 'edit'])->name('sms.providers.edit');
        Route::put('/sms/providers/{provider}', [SmsProviderAccountController::class, 'update'])->name('sms.providers.update');
        Route::delete('/sms/providers/{provider}', [SmsProviderAccountController::class, 'destroy'])->name('sms.providers.destroy');
        Route::get('/sms/logs', [SmsDeliveryLogController::class, 'index'])->name('sms.logs.index');
        Route::get('/sms/logs/{log}', [SmsDeliveryLogController::class, 'show'])->name('sms.logs.show');
        Route::get('/sms/statistics', [SmsDeliveryLogController::class, 'statistics'])->name('sms.statistics.index');
        Route::get('/account/sms-settings', [UserSmsSettingController::class, 'edit'])->name('account.sms-settings.edit');
        Route::put('/account/sms-settings', [UserSmsSettingController::class, 'update'])->name('account.sms-settings.update');
        Route::post('/account/sms-settings/test-send', [UserSmsSettingController::class, 'testSend'])->name('account.sms-settings.test-send');
    });
});
