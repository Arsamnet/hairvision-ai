<?php

use App\Http\Controllers\Admin\AdminGuideController;
use App\Http\Controllers\Admin\AiModelAccessController;
use App\Http\Controllers\Admin\AiModelCategoryController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ClinicAiModelAccessController;
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\ClinicNotificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LandingTemplateController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadFollowUpController;
use App\Http\Controllers\Admin\LeadGeneratedAssetController;
use App\Http\Controllers\Admin\LeadSourceImageController;
use App\Http\Controllers\Admin\LeadUploadController;
use App\Http\Controllers\Admin\ManagementReportController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SmsGatewayController;
use App\Http\Controllers\Admin\SystemClinicNotificationController;
use App\Http\Controllers\Admin\SystemDashboardController;
use App\Http\Controllers\Admin\SystemGuideController;
use App\Http\Controllers\Admin\SystemGuideFaqController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TenantSwitchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserSavedFilterController;
use App\Http\Middleware\EnsureAdminTenantSelected;
use App\Models\TenantDomain;
use App\Services\Landing\LandingTemplateResolver;
use App\Services\LandingPageVisitService;
use App\Support\Tenancy\HostClassifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function (
    Request $request,
    LandingPageVisitService $visitService,
    LandingTemplateResolver $landingTemplates,
) {
    $host = HostClassifier::normalize($request->getHost());
    $domain = TenantDomain::query()
        ->with('tenant.theme.template')
        ->where('domain', $host)
        ->where('status', 'active')
        ->first();

    if (
        HostClassifier::isPlatformHost($host, $domain)
        || (app()->environment('local') && in_array($host, ['127.0.0.1', 'localhost', '::1'], true))
    ) {
        return view('public.landing');
    }

    abort_unless($domain && $domain->tenant && $domain->tenant->status === 'active', Response::HTTP_NOT_FOUND);

    $tenant = $domain->tenant;
    app()->instance('currentTenant', $tenant);
    $request->attributes->set('currentTenant', $tenant);
    $visitService->record($request, $tenant);

    return view('landing', [
        'tenant' => $tenant,
        'landingTheme' => $landingTemplates->resolve($tenant),
    ]);
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
        Route::get('/notifications', [ClinicNotificationController::class, 'index'])->middleware('permission:notifications.view')->name('notifications.index');
        Route::patch('/notifications/read-all', [ClinicNotificationController::class, 'markAllRead'])->middleware('permission:notifications.view')->name('notifications.read-all');
        Route::get('/notifications/{notification}', [ClinicNotificationController::class, 'show'])->middleware('permission:notifications.view')->name('notifications.show');
        Route::get('/help/guide', [AdminGuideController::class, 'show'])->name('guide.show');
        Route::get('/help/faq', [AdminGuideController::class, 'faq'])->name('guide.faq');
        Route::prefix('crm')->group(function (): void {
            Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
            Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create');
            Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
            Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
            Route::get('/leads/{lead}/source-images/{leadSourceImage}', [LeadSourceImageController::class, 'show'])->name('leads.source-images.show');
            Route::get('/leads/{lead}/uploads/{upload}', [LeadUploadController::class, 'show'])->name('leads.uploads.show');
            Route::get('/leads/{lead}/assets/{generatedAsset}', [LeadGeneratedAssetController::class, 'show'])->name('leads.assets.show');
            Route::post('/leads/{lead}/follow-ups', [LeadFollowUpController::class, 'store'])->name('leads.follow-ups.store');
            Route::patch('/leads/{lead}/follow-ups/{leadNote}', [LeadFollowUpController::class, 'update'])->name('leads.follow-ups.update');
            Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
            Route::put('/leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        });

        Route::prefix('security')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.list')->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
            Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('users.show');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.edit')->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.edit')->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.list')->name('system.roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('system.roles.store');
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.view')->name('system.roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.edit')->name('system.roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('system.roles.destroy');
            Route::get('/permissions', PermissionController::class)->middleware('permission:permissions.list')->name('system.permissions.index');
        });

        Route::prefix('finance')->group(function (): void {
            Route::get('/plans', [PlanController::class, 'index'])->middleware('permission:plans.list')->name('plans.index');
            Route::get('/plans/{plan}', [PlanController::class, 'show'])->middleware('permission:plans.list')->name('plans.show');
            Route::post('/plans/{plan}/purchase', [PlanController::class, 'purchase'])->middleware('permission:billing.pay')->name('plans.purchase');
            Route::post('/plans', [PlanController::class, 'store'])->middleware('permission:plans.create,plans.manage')->name('system.plans.store');
            Route::put('/plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plans.edit,plans.manage')->name('system.plans.update');
            Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plans.delete,plans.manage')->name('system.plans.destroy');

            Route::get('/billing', [BillingController::class, 'index'])->middleware('permission:billing.list')->name('billing.index');
            Route::match(['get', 'post'], '/billing/invoices/{invoice}/pay', [BillingController::class, 'pay'])->middleware('permission:billing.pay')->name('billing.pay');
            Route::match(['get', 'post'], '/billing/payments/{payment}/verify', [BillingController::class, 'verify'])->middleware('permission:billing.pay')->name('billing.verify');
            Route::get('/billing/payments/{payment}/result', [BillingController::class, 'result'])->middleware('permission:billing.pay')->name('billing.result');

            Route::get('/payment-gateways', [PaymentGatewayController::class, 'index'])->middleware('permission:payment_gateways.list')->name('system.payment-gateways.index');
            Route::post('/payment-gateways', [PaymentGatewayController::class, 'store'])->middleware('permission:payment_gateways.create')->name('system.payment-gateways.store');
            Route::put('/payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->middleware('permission:payment_gateways.edit')->name('system.payment-gateways.update');
        });

        Route::prefix('reports')->group(function (): void {
            Route::get('/performance', [DashboardController::class, 'reports'])->middleware('permission:reports.user.view')->name('reports');
            Route::get('/credit-usage', [DashboardController::class, 'creditUsageReport'])->middleware('permission:reports.user.view')->name('reports.credit-usage');
            Route::get('/management', ManagementReportController::class)->middleware('permission:reports.management.view')->name('system.reports.index');
        });

        Route::prefix('settings')->group(function (): void {
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
            Route::delete('/clinics/{clinic}/sms-gateway', [ClinicController::class, 'destroySmsGateway'])->middleware('permission:sms_gateways.delete')->name('clinics.sms-gateway.destroy');
            Route::get('/clinics/{clinic}', [ClinicController::class, 'show'])->middleware('permission:tenants.view')->name('clinics.show');
            Route::get('/clinics/{clinic}/edit', [ClinicController::class, 'edit'])->middleware('permission:tenants.edit')->name('clinics.edit');
            Route::put('/clinics/{clinic}', [ClinicController::class, 'update'])->middleware('permission:tenants.edit')->name('clinics.update');
            Route::delete('/clinics/{clinic}', [ClinicController::class, 'destroy'])->middleware('permission:tenants.delete')->name('clinics.destroy');

            Route::get('/templates', [LandingTemplateController::class, 'index'])->middleware('permission:landing_templates.view')->name('system.landing-templates.index');
            Route::get('/templates/create', [LandingTemplateController::class, 'create'])->middleware('permission:landing_templates.create')->name('system.landing-templates.create');
            Route::post('/templates', [LandingTemplateController::class, 'store'])->middleware('permission:landing_templates.create')->name('system.landing-templates.store');
            Route::get('/templates/clinics', [LandingTemplateController::class, 'clinics'])->middleware('permission:landing_templates.assign')->name('system.landing-templates.clinics');
            Route::put('/templates/clinics/{clinic}', [LandingTemplateController::class, 'assign'])->middleware('permission:landing_templates.assign')->name('system.landing-templates.clinics.assign');
            Route::get('/templates/{landingTemplate}/edit', [LandingTemplateController::class, 'edit'])->middleware('permission:landing_templates.update')->name('system.landing-templates.edit');
            Route::put('/templates/{landingTemplate}', [LandingTemplateController::class, 'update'])->middleware('permission:landing_templates.update')->name('system.landing-templates.update');
            Route::patch('/templates/{landingTemplate}/default', [LandingTemplateController::class, 'makeDefault'])->middleware('permission:landing_templates.update')->name('system.landing-templates.default');
            Route::delete('/templates/{landingTemplate}', [LandingTemplateController::class, 'destroy'])->middleware('permission:landing_templates.delete')->name('system.landing-templates.destroy');

            Route::get('/notifications', [SystemClinicNotificationController::class, 'index'])->middleware('permission:notifications.manage')->name('system.notifications.index');
            Route::post('/notifications', [SystemClinicNotificationController::class, 'store'])->middleware('permission:notifications.manage')->name('system.notifications.store');
            Route::get('/notifications/{notification}', [SystemClinicNotificationController::class, 'show'])->middleware('permission:notifications.manage')->name('system.notifications.show');

            Route::get('/ai-applications', [AiModelCategoryController::class, 'index'])->middleware('permission:ai_model_categories.view')->name('system.ai-model-categories.index');
            Route::post('/ai-applications', [AiModelCategoryController::class, 'store'])->middleware('permission:ai_model_categories.create')->name('system.ai-model-categories.store');
            Route::get('/ai-applications/{category}', [AiModelCategoryController::class, 'show'])->middleware('permission:ai_model_categories.view')->name('system.ai-model-categories.show');
            Route::put('/ai-applications/{category}', [AiModelCategoryController::class, 'update'])->middleware('permission:ai_model_categories.update')->name('system.ai-model-categories.update');
            Route::patch('/ai-applications/{category}/toggle', [AiModelCategoryController::class, 'toggle'])->middleware('permission:ai_model_categories.update')->name('system.ai-model-categories.toggle');
            Route::patch('/ai-applications/{category}/archive', [AiModelCategoryController::class, 'archive'])->middleware('permission:ai_model_categories.update')->name('system.ai-model-categories.archive');
            Route::delete('/ai-applications/{category}', [AiModelCategoryController::class, 'destroy'])->middleware('permission:ai_model_categories.delete')->name('system.ai-model-categories.destroy');
            Route::post('/ai-applications/{category}/outputs', [AiModelCategoryController::class, 'storeOutput'])->middleware('permission:ai_model_category_outputs.manage')->name('system.ai-model-categories.outputs.store');
            Route::put('/ai-applications/{category}/outputs/{output}', [AiModelCategoryController::class, 'updateOutput'])->middleware('permission:ai_model_category_outputs.manage')->name('system.ai-model-categories.outputs.update');
            Route::delete('/ai-applications/{category}/outputs/{output}', [AiModelCategoryController::class, 'destroyOutput'])->middleware('permission:ai_model_category_outputs.manage')->name('system.ai-model-categories.outputs.destroy');

            Route::get('/ai-models', [AiModelAccessController::class, 'index'])->middleware('permission:ai_models.list')->name('ai-models.index');
            Route::post('/ai-models', [AiModelAccessController::class, 'store'])->middleware('permission:ai_models.create')->name('ai-models.store');
            Route::get('/ai-models/{aiModelAccess}/edit', [AiModelAccessController::class, 'edit'])->middleware('permission:ai_models.edit')->name('ai-models.edit');
            Route::get('/ai-models/{aiModelAccess}', [AiModelAccessController::class, 'show'])->middleware('permission:ai_models.view')->name('ai-models.show');
            Route::put('/ai-models/{aiModelAccess}', [AiModelAccessController::class, 'update'])->middleware('permission:ai_models.edit')->name('ai-models.update');
            Route::delete('/ai-models/{aiModelAccess}', [AiModelAccessController::class, 'destroy'])->middleware('permission:ai_models.delete')->name('ai-models.destroy');
            Route::patch('/ai-models/{aiModelAccess}/toggle', [AiModelAccessController::class, 'toggle'])->middleware('permission:ai_models.toggle')->name('ai-models.toggle');

            Route::get('/sms-gateways', [SmsGatewayController::class, 'index'])->middleware('permission:sms_gateways.list')->name('system.sms-gateways.index');
            Route::get('/sms-gateways/create', [SmsGatewayController::class, 'create'])->middleware('permission:sms_gateways.create')->name('system.sms-gateways.create');
            Route::post('/sms-gateways', [SmsGatewayController::class, 'store'])->middleware('permission:sms_gateways.create')->name('system.sms-gateways.store');
            Route::get('/sms-gateways/{smsGateway}', [SmsGatewayController::class, 'show'])->middleware('permission:sms_gateways.view')->name('system.sms-gateways.show');
            Route::get('/sms-gateways/{smsGateway}/edit', [SmsGatewayController::class, 'edit'])->middleware('permission:sms_gateways.edit')->name('system.sms-gateways.edit');
            Route::put('/sms-gateways/{smsGateway}', [SmsGatewayController::class, 'update'])->middleware('permission:sms_gateways.edit')->name('system.sms-gateways.update');
            Route::delete('/sms-gateways/{smsGateway}', [SmsGatewayController::class, 'destroy'])->middleware('permission:sms_gateways.delete')->name('system.sms-gateways.destroy');

            Route::get('/general', [SystemSettingController::class, 'edit'])->middleware('permission:settings.manage')->name('system.settings.edit');
            Route::put('/general', [SystemSettingController::class, 'update'])->middleware('permission:settings.manage')->name('system.settings.update');
            Route::middleware('permission:guide.manage')->group(function (): void {
                Route::get('/guide', [SystemGuideController::class, 'edit'])->name('system.guide.edit');
                Route::put('/guide', [SystemGuideController::class, 'update'])->name('system.guide.update');
                Route::post('/guide/sections', [SystemGuideController::class, 'storeSection'])->name('system.guide.sections.store');
                Route::put('/guide/sections/{guideSection}', [SystemGuideController::class, 'updateSection'])->name('system.guide.sections.update');
                Route::patch('/guide/sections/{guideSection}/move', [SystemGuideController::class, 'moveSection'])->name('system.guide.sections.move');
                Route::delete('/guide/sections/{guideSection}', [SystemGuideController::class, 'destroySection'])->name('system.guide.sections.destroy');
                Route::post('/guide/sections/{guideSection}/steps', [SystemGuideController::class, 'storeStep'])->name('system.guide.steps.store');
                Route::put('/guide/steps/{guideStep}', [SystemGuideController::class, 'updateStep'])->name('system.guide.steps.update');
                Route::patch('/guide/steps/{guideStep}/move', [SystemGuideController::class, 'moveStep'])->name('system.guide.steps.move');
                Route::delete('/guide/steps/{guideStep}', [SystemGuideController::class, 'destroyStep'])->name('system.guide.steps.destroy');

                Route::get('/faqs', [SystemGuideFaqController::class, 'edit'])->name('system.faq.edit');
                Route::put('/faqs', [SystemGuideFaqController::class, 'update'])->name('system.faq.update');
                Route::post('/faqs/items', [SystemGuideFaqController::class, 'store'])->name('system.faq.items.store');
                Route::put('/faqs/items/{guideFaq}', [SystemGuideFaqController::class, 'updateItem'])->name('system.faq.items.update');
                Route::patch('/faqs/items/{guideFaq}/move', [SystemGuideFaqController::class, 'move'])->name('system.faq.items.move');
                Route::delete('/faqs/items/{guideFaq}', [SystemGuideFaqController::class, 'destroy'])->name('system.faq.items.destroy');
            });
        });

        Route::get('/settings/overview', SystemDashboardController::class)->middleware('permission:system.dashboard')->name('system.dashboard');

        Route::redirect('/leads', '/admin/crm/leads', 301)->name('legacy.leads');
        Route::redirect('/users', '/admin/security/users', 301)->name('legacy.users');
        Route::redirect('/plans', '/admin/finance/plans', 301)->name('legacy.plans');
        Route::redirect('/billing', '/admin/finance/billing', 301)->name('legacy.billing');
        Route::redirect('/reports', '/admin/reports/performance', 301)->name('legacy.reports');
        Route::redirect('/clinics', '/admin/settings/clinics', 301)->name('legacy.clinics');
        Route::redirect('/ai-models', '/admin/settings/ai-models', 301)->name('legacy.ai-models');
        Route::redirect('/system/roles', '/admin/security/roles', 301)->name('legacy.roles');
        Route::redirect('/system/permissions', '/admin/security/permissions', 301)->name('legacy.permissions');
        Route::redirect('/system/payment-gateways', '/admin/finance/payment-gateways', 301)->name('legacy.payment-gateways');
        Route::redirect('/system/landing-templates', '/admin/settings/templates', 301)->name('legacy.templates');
        Route::redirect('/system/notifications', '/admin/settings/notifications', 301)->name('legacy.system-notifications');
        Route::redirect('/system/ai-model-categories', '/admin/settings/ai-applications', 301)->name('legacy.ai-applications');
        Route::redirect('/system/sms-gateways', '/admin/settings/sms-gateways', 301)->name('legacy.sms-gateways');
        Route::redirect('/system/settings', '/admin/settings/general', 301)->name('legacy.settings');
        Route::redirect('/system/reports', '/admin/reports/management', 301)->name('legacy.management-reports');
        Route::redirect('/system', '/admin/settings/overview', 301)->name('legacy.system-overview');
    });
});
