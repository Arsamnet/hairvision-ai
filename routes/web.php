<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\TenantSwitchController;
use App\Http\Controllers\Admin\UserController;
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
    });
});
