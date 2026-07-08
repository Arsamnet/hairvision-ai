<?php

use App\Http\Controllers\Admin\AiModelAccessController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\ClinicAiModelAccessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadGeneratedAssetController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadFollowUpController;
use App\Http\Controllers\Admin\LeadUploadController;
use App\Http\Controllers\Admin\ManagementReportController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SmsGatewayController;
use App\Http\Controllers\Admin\SystemDashboardController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TenantSwitchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserSavedFilterController;
use App\Http\Middleware\EnsureAdminTenantSelected;
use App\Models\TenantDomain;
use App\Services\LandingPageVisitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request, LandingPageVisitService $visitService) {
    $host = strtolower($request->getHost());
    $domain = TenantDomain::query()
        ->with('tenant.theme')
        ->where('domain', $host)
        ->where('status', 'active')
        ->first();

    if (
        $host === config('hairvision.primary_domain')
        || $domain?->type === 'platform'
        || (app()->environment('local') && in_array($host, ['127.0.0.1', 'localhost', '::1'], true))
    ) {
        return view('public.landing');
    }

    abort_unless($domain && $domain->tenant && $domain->tenant->status === 'active', Response::HTTP_NOT_FOUND);

    $tenant = $domain->tenant;
    app()->instance('currentTenant', $tenant);
    $request->attributes->set('currentTenant', $tenant);
    $visitService->record($request, $tenant);

    return view('landing', ['tenant' => $tenant, 'theme' => $tenant->theme]);
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])->name('otp.request');
    Route::get('/otp', [AuthController::class, 'showVerify'])->name('otp.form');
    Route::post('/otp/verify', [AuthController::class, 'verify'])->name('otp.verify');

    Route::middleware(['auth', EnsureAdminTenantSelected::class, 'active_plan'])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/tenant', TenantSwitchController::class)->name('tenant.switch');
        Route::post('/filter-presets', [UserSavedFilterController::class, 'store'])->name('filter-presets.store');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::get('/leads/{lead}/uploads/{upload}', [LeadUploadController::class, 'show'])->name('leads.uploads.show');
        Route::get('/leads/{lead}/assets/{generatedAsset}', [LeadGeneratedAssetController::class, 'show'])->name('leads.assets.show');
        Route::post('/leads/{lead}/follow-ups', [LeadFollowUpController::class, 'store'])->name('leads.follow-ups.store');
        Route::patch('/leads/{lead}/follow-ups/{leadNote}', [LeadFollowUpController::class, 'update'])->name('leads.follow-ups.update');
        Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
        Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.list')->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
        Route::get('/ai-models', [AiModelAccessController::class, 'index'])->middleware('permission:ai_models.list')->name('ai-models.index');
        Route::post('/ai-models', [AiModelAccessController::class, 'store'])->middleware('permission:ai_models.create')->name('ai-models.store');
        Route::get('/ai-models/{aiModelAccess}', [AiModelAccessController::class, 'show'])->middleware('permission:ai_models.view')->name('ai-models.show');
        Route::put('/ai-models/{aiModelAccess}', [AiModelAccessController::class, 'update'])->middleware('permission:ai_models.edit')->name('ai-models.update');
        Route::delete('/ai-models/{aiModelAccess}', [AiModelAccessController::class, 'destroy'])->middleware('permission:ai_models.delete')->name('ai-models.destroy');
        Route::patch('/ai-models/{aiModelAccess}/toggle', [AiModelAccessController::class, 'toggle'])->middleware('permission:ai_models.toggle')->name('ai-models.toggle');
        Route::get('/clinics/{clinic}/ai-models', [ClinicAiModelAccessController::class, 'index'])->middleware('permission:ai_models.assign_to_clinic')->name('clinics.ai-models.index');
        Route::post('/clinics/{clinic}/ai-models', [ClinicAiModelAccessController::class, 'store'])->middleware('permission:ai_models.assign_to_clinic')->name('clinics.ai-models.store');
        Route::patch('/clinics/{clinic}/ai-models/{aiModelAccess}', [ClinicAiModelAccessController::class, 'update'])->middleware('permission:ai_models.assign_to_clinic')->name('clinics.ai-models.update');
        Route::delete('/clinics/{clinic}/ai-models/{aiModelAccess}', [ClinicAiModelAccessController::class, 'destroy'])->middleware('permission:ai_models.assign_to_clinic')->name('clinics.ai-models.destroy');
        Route::get('/clinics', [ClinicController::class, 'index'])->middleware('permission:tenants.list')->name('clinics.index');
        Route::post('/clinics', [ClinicController::class, 'store'])->middleware('permission:tenants.create')->name('clinics.store');
        Route::post('/clinics/{clinic}/plans', [ClinicController::class, 'assignPlan'])->middleware('permission:billing.manage')->name('clinics.plans.assign');
        Route::post('/clinics/{clinic}/invoices', [ClinicController::class, 'storeInvoice'])->middleware('permission:billing.manage')->name('clinics.invoices.store');
        Route::put('/clinics/{clinic}/invoices/{invoice}', [ClinicController::class, 'updateInvoice'])->middleware('permission:billing.manage')->name('clinics.invoices.update');
        Route::patch('/clinics/{clinic}/invoices/{invoice}/approve', [ClinicController::class, 'approveInvoice'])->middleware('permission:billing.manage')->name('clinics.invoices.approve');
        Route::post('/clinics/{clinic}/sms-gateway', [ClinicController::class, 'updateSmsGateway'])->middleware('permission:sms_gateways.edit,sms_gateways.manage')->name('clinics.sms-gateway.update');
        Route::get('/clinics/{clinic}', [ClinicController::class, 'show'])->middleware('permission:tenants.view')->name('clinics.show');
        Route::get('/clinics/{clinic}/edit', [ClinicController::class, 'edit'])->middleware('permission:tenants.edit')->name('clinics.edit');
        Route::put('/clinics/{clinic}', [ClinicController::class, 'update'])->middleware('permission:tenants.edit')->name('clinics.update');
        Route::delete('/clinics/{clinic}', [ClinicController::class, 'destroy'])->middleware('permission:tenants.delete')->name('clinics.destroy');
        Route::get('/reports', [DashboardController::class, 'reports'])->middleware('permission:reports.user.view')->name('reports');
        Route::get('/plans', [PlanController::class, 'index'])->middleware('permission:plans.list')->name('plans.index');
        Route::post('/plans/{plan}/purchase', [PlanController::class, 'purchase'])->middleware('permission:billing.pay')->name('plans.purchase');
        Route::get('/billing', [BillingController::class, 'index'])->middleware('permission:billing.list')->name('billing.index');
        Route::match(['get', 'post'], '/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])->middleware('permission:billing.pay')->name('billing.pay');
        Route::match(['get', 'post'], '/billing/payments/{payment}/verify', [BillingController::class, 'verify'])->middleware('permission:billing.pay')->name('billing.verify');
        Route::prefix('system')->name('system.')->group(function (): void {
            Route::get('/', SystemDashboardController::class)->middleware('permission:system.dashboard')->name('dashboard');
            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.list')->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.view')->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
            Route::get('/permissions', PermissionController::class)->middleware('permission:permissions.list')->name('permissions.index');
            Route::get('/settings', [SystemSettingController::class, 'edit'])->middleware('permission:settings.manage')->name('settings.edit');
            Route::put('/settings', [SystemSettingController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
            Route::get('/sms-gateways', [SmsGatewayController::class, 'index'])->middleware('permission:sms_gateways.list')->name('sms-gateways.index');
            Route::get('/sms-gateways/create', [SmsGatewayController::class, 'create'])->middleware('permission:sms_gateways.create')->name('sms-gateways.create');
            Route::post('/sms-gateways', [SmsGatewayController::class, 'store'])->middleware('permission:sms_gateways.create')->name('sms-gateways.store');
            Route::get('/sms-gateways/{smsGateway}', [SmsGatewayController::class, 'show'])->middleware('permission:sms_gateways.view')->name('sms-gateways.show');
            Route::get('/sms-gateways/{smsGateway}/edit', [SmsGatewayController::class, 'edit'])->middleware('permission:sms_gateways.edit')->name('sms-gateways.edit');
            Route::put('/sms-gateways/{smsGateway}', [SmsGatewayController::class, 'update'])->middleware('permission:sms_gateways.edit')->name('sms-gateways.update');
            Route::delete('/sms-gateways/{smsGateway}', [SmsGatewayController::class, 'destroy'])->middleware('permission:sms_gateways.delete')->name('sms-gateways.destroy');
            Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->middleware('permission:payment_gateways.list')->name('payment-gateways.index');
            Route::post('/payment-gateways', [PaymentGatewayController::class, 'store'])->middleware('permission:payment_gateways.create')->name('payment-gateways.store');
            Route::put('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->middleware('permission:payment_gateways.edit')->name('payment-gateways.update');
            Route::post('/plans', [PlanController::class, 'store'])->middleware('permission:plans.create,plans.manage')->name('plans.store');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plans.edit,plans.manage')->name('plans.update');
            Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plans.delete,plans.manage')->name('plans.destroy');
            Route::get('/reports', ManagementReportController::class)->middleware('permission:reports.management.view')->name('reports.index');
        });
    });
});
