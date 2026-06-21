<?php

use App\Http\Middleware\ResolveTenantByHost;
use App\Services\LandingPageVisitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveTenantByHost::class])->get('/', function (Request $request, LandingPageVisitService $visitService) {
    $tenant = app('currentTenant');
    $visitService->record($request, $tenant);

    return view('landing', ['tenant' => $tenant, 'theme' => $tenant->theme]);
});
