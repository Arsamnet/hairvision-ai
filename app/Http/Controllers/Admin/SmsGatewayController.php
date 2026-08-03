<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SmsGateways\StoreSmsGatewayRequest;
use App\Http\Requests\Admin\SmsGateways\UpdateSmsGatewayRequest;
use App\Models\SmsGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\SmsGatewayManagementService;
use App\Services\Sms\DebugSmsGatewayAvailability;
use App\Services\Sms\SmsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manages platform and clinic SMS gateway records.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class SmsGatewayController extends Controller
{
    /**
     * Display the sms gateway listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @param  DebugSmsGatewayAvailability  $debugGateway  Runtime debug gateway boundary.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function index(
        Request $request,
        SmsGatewayManagementService $manager,
        DebugSmsGatewayAvailability $debugGateway
    ): View {
        Gate::authorize('viewAny', SmsGateway::class);

        $gateways = $manager->paginateSystem($request);
        $effectiveStatuses = collect($gateways->items())
            ->mapWithKeys(fn (SmsGateway $gateway): array => [
                (string) $gateway->getKey() => $debugGateway->effectiveStatus($gateway),
            ])
            ->all();

        return view('admin.system.sms-gateways.index', $this->formData($request, $manager) + [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => $gateways,
            'effectiveStatuses' => $effectiveStatuses,
        ]);
    }

    /**
     * Display the form for creating a new sms gateway.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function create(Request $request, SmsGatewayManagementService $manager): View
    {
        Gate::authorize('create', SmsGateway::class);

        return view('admin.system.sms-gateways.create', $this->formData($request, $manager));
    }

    /**
     * Persist a new sms gateway record.
     *
     * @param  StoreSmsGatewayRequest  $request  Incoming HTTP request.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @param  SmsGatewayService  $gateways  The gateways value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function store(StoreSmsGatewayRequest $request, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $gateway = $manager->createSystem($request->validated(), $gateways);

        if ($request->boolean('return_to_index')) {
            return redirect()->route('admin.system.sms-gateways.index')->with('status', __('sms.created'));
        }

        return redirect()->route('admin.system.sms-gateways.show', $gateway)->with('status', __('sms.created'));
    }

    /**
     * Display the requested sms gateway resource.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SmsGateway  $smsGateway  The sms gateway value.
     * @param  DebugSmsGatewayAvailability  $debugGateway  Runtime debug gateway boundary.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function show(
        Request $request,
        SmsGateway $smsGateway,
        DebugSmsGatewayAvailability $debugGateway
    ): View {
        Gate::authorize('view', $smsGateway);

        return view('admin.system.sms-gateways.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateway' => $smsGateway->load(['user', 'tenant']),
            'effectiveStatus' => $debugGateway->effectiveStatus($smsGateway),
        ]);
    }

    /**
     * Display the form for editing the requested sms gateway.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SmsGateway  $smsGateway  The sms gateway value.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function edit(Request $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager): View
    {
        Gate::authorize('update', $smsGateway);

        return view('admin.system.sms-gateways.edit', $this->formData($request, $manager, $smsGateway) + [
            'gateway' => $smsGateway->load(['user', 'tenant']),
        ]);
    }

    /**
     * Update the requested sms gateway record.
     *
     * @param  UpdateSmsGatewayRequest  $request  Incoming HTTP request.
     * @param  SmsGateway  $smsGateway  The sms gateway value.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @param  SmsGatewayService  $gateways  The gateways value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function update(UpdateSmsGatewayRequest $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $manager->updateSystem($smsGateway, $request->validated(), $gateways);

        return redirect()->route('admin.system.sms-gateways.show', $smsGateway)->with('status', __('sms.updated'));
    }

    /**
     * Select the requested SMS gateway as the default for its owner scope.
     *
     * @param  SmsGateway  $smsGateway  SMS gateway being selected.
     * @param  SmsGatewayService  $gateways  SMS gateway domain service.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 2.1.0
     */
    public function markDefault(SmsGateway $smsGateway, SmsGatewayService $gateways): RedirectResponse
    {
        Gate::authorize('markDefault', $smsGateway);

        $gateways->markDefault($smsGateway);

        return back()->with('status', __('sms.default_updated'));
    }

    /**
     * Delete the requested sms gateway record.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SmsGateway  $smsGateway  The sms gateway value.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function destroy(Request $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager): RedirectResponse
    {
        Gate::authorize('delete', $smsGateway);

        $manager->deleteSystem($smsGateway);

        return redirect()->route('admin.system.sms-gateways.index')->with('status', __('sms.deleted'));
    }

    /**
     * Execute the form data operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SmsGatewayManagementService|null  $manager  The manager value.
     * @param  SmsGateway|null  $gateway  The gateway value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function formData(Request $request, ?SmsGatewayManagementService $manager = null, ?SmsGateway $gateway = null): array
    {
        $manager ??= app(SmsGatewayManagementService::class);

        return [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'users' => User::query()->orderBy('name')->get(),
            'tenants' => Tenant::query()->where('status', 'active')->orderBy('name')->get(),
            'providerOptions' => $manager->providerOptionsForSystem($gateway),
        ];
    }
}
