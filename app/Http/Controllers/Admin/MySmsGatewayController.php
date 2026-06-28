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
        return view('admin.sms-gateways.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => $manager->paginatePrivate($request, $request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.sms-gateways.create', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
        ]);
    }

    public function store(StorePrivateSmsGatewayRequest $request, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $gateway = $manager->createPrivate($request->user(), $request->validated(), $gateways);

        if ($request->boolean('return_to_index')) {
            return redirect()->route('admin.sms-gateways.index')->with('status', __('sms.private_created'));
        }

        return redirect()->route('admin.sms-gateways.show', $gateway)->with('status', __('sms.private_created'));
    }

    public function show(Request $request, SmsGateway $smsGateway): View
    {
        $this->authorizeOwner($request, $smsGateway);
        Gate::authorize('view', $smsGateway);

        return view('admin.sms-gateways.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateway' => $smsGateway,
        ]);
    }

    public function edit(Request $request, SmsGateway $smsGateway): View
    {
        $this->authorizeOwner($request, $smsGateway);
        Gate::authorize('update', $smsGateway);

        return view('admin.sms-gateways.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateway' => $smsGateway,
        ]);
    }

    public function update(UpdatePrivateSmsGatewayRequest $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $this->authorizeOwner($request, $smsGateway);
        $manager->updatePrivate($smsGateway, $request->validated(), $gateways);

        return redirect()->route('admin.sms-gateways.show', $smsGateway)->with('status', __('sms.private_updated'));
    }

    public function destroy(Request $request, SmsGateway $smsGateway): RedirectResponse
    {
        $this->authorizeOwner($request, $smsGateway);
        Gate::authorize('delete', $smsGateway);

        $smsGateway->delete();

        return redirect()->route('admin.sms-gateways.index')->with('status', __('sms.private_deleted'));
    }

    private function authorizeOwner(Request $request, SmsGateway $smsGateway): void
    {
        abort_unless($smsGateway->owner_scope === 'user' && $smsGateway->user_id === $request->user()->id, 404);
    }
}
