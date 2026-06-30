<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SmsGateways\StorePrivateSmsGatewayRequest;
use App\Http\Requests\Admin\SmsGateways\UpdatePrivateSmsGatewayRequest;
use App\Models\SmsGateway;
use App\Services\Admin\SmsGatewayManagementService;
use App\Services\Sms\SmsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Lets a dashboard user configure private SMS gateways for own customers.
 *
 * @since 1.0.0
 */
class MySmsGatewayController extends Controller
{
    public function index(Request $request, SmsGatewayManagementService $manager): View
    {
        $gateway = $manager->privateSettings($request->user());

        return view('admin.sms-gateways.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateway' => $gateway,
            'providerOptions' => $manager->providerOptionsForPrivate($gateway),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.sms-gateways.index');
    }

    public function store(StorePrivateSmsGatewayRequest $request, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $manager->savePrivateSettings($request->user(), $request->validated(), $gateways);

        return redirect()->route('admin.sms-gateways.index')->with('status', __('sms.private_updated'));
    }

    public function show(Request $request, SmsGateway $smsGateway): RedirectResponse
    {
        $this->authorizeOwner($request, $smsGateway);
        Gate::authorize('view', $smsGateway);

        return redirect()->route('admin.sms-gateways.index');
    }

    public function edit(Request $request, SmsGateway $smsGateway): RedirectResponse
    {
        $this->authorizeOwner($request, $smsGateway);
        Gate::authorize('update', $smsGateway);

        return redirect()->route('admin.sms-gateways.index');
    }

    public function update(UpdatePrivateSmsGatewayRequest $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $this->authorizeOwner($request, $smsGateway);
        $manager->savePrivateSettings($request->user(), $request->validated(), $gateways);

        return redirect()->route('admin.sms-gateways.index')->with('status', __('sms.private_updated'));
    }

    public function destroy(Request $request, SmsGateway $smsGateway): RedirectResponse
    {
        $this->authorizeOwner($request, $smsGateway);
        Gate::authorize('delete', $smsGateway);

        $smsGateway->forceFill(['status' => 'disabled'])->save();

        return redirect()->route('admin.sms-gateways.index')->with('status', __('sms.private_disabled'));
    }

    private function authorizeOwner(Request $request, SmsGateway $smsGateway): void
    {
        abort_unless($smsGateway->owner_scope === 'user' && $smsGateway->user_id === $request->user()->id, 404);
    }
}
