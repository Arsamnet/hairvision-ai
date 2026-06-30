<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SmsGateways\StoreSmsGatewayRequest;
use App\Http\Requests\Admin\SmsGateways\UpdateSmsGatewayRequest;
use App\Models\SmsGateway;
use App\Models\User;
use App\Services\Admin\SmsGatewayManagementService;
use App\Services\Sms\SmsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manages platform SMS gateways and user-private gateway records.
 *
 * @since 1.0.0
 */
class SmsGatewayController extends Controller
{
    public function index(Request $request, SmsGatewayManagementService $manager): View
    {
        Gate::authorize('viewAny', SmsGateway::class);

        return view('admin.system.sms-gateways.index', $this->formData($request, $manager) + [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => $manager->paginateSystem($request),
        ]);
    }

    public function create(Request $request, SmsGatewayManagementService $manager): View
    {
        Gate::authorize('create', SmsGateway::class);

        return view('admin.system.sms-gateways.create', $this->formData($request, $manager));
    }

    public function store(StoreSmsGatewayRequest $request, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $gateway = $manager->createSystem($request->validated(), $gateways);

        if ($request->boolean('return_to_index')) {
            return redirect()->route('admin.system.sms-gateways.index')->with('status', __('sms.created'));
        }

        return redirect()->route('admin.system.sms-gateways.show', $gateway)->with('status', __('sms.created'));
    }

    public function show(Request $request, SmsGateway $smsGateway): View
    {
        Gate::authorize('view', $smsGateway);

        return view('admin.system.sms-gateways.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateway' => $smsGateway->load('user'),
        ]);
    }

    public function edit(Request $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager): View
    {
        Gate::authorize('update', $smsGateway);

        return view('admin.system.sms-gateways.edit', $this->formData($request, $manager, $smsGateway) + [
            'gateway' => $smsGateway->load('user'),
        ]);
    }

    public function update(UpdateSmsGatewayRequest $request, SmsGateway $smsGateway, SmsGatewayManagementService $manager, SmsGatewayService $gateways): RedirectResponse
    {
        $manager->updateSystem($smsGateway, $request->validated(), $gateways);

        return redirect()->route('admin.system.sms-gateways.show', $smsGateway)->with('status', __('sms.updated'));
    }

    public function destroy(Request $request, SmsGateway $smsGateway): RedirectResponse
    {
        Gate::authorize('delete', $smsGateway);

        $smsGateway->delete();

        return redirect()->route('admin.system.sms-gateways.index')->with('status', __('sms.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?SmsGatewayManagementService $manager = null, ?SmsGateway $gateway = null): array
    {
        $manager ??= app(SmsGatewayManagementService::class);

        return [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'users' => User::query()->orderBy('name')->get(),
            'providerOptions' => $manager->providerOptionsForSystem($gateway),
        ];
    }
}
