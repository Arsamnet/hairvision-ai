<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsGateway;
use App\Services\Sms\SmsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a dashboard user configure private SMS gateways for own customers.
 *
 * @since 1.0.0
 */
class MySmsGatewayController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.sms-gateways.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => $request->user()->smsGateways()->latest()->paginate(10),
        ]);
    }

    public function store(Request $request, SmsGatewayService $gateways): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80'],
            'status' => ['required', 'in:enabled,disabled'],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
        ]);

        $gateway = SmsGateway::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'provider_code' => $validated['provider_code'],
            'owner_scope' => 'user',
            'status' => $validated['status'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'encrypted_credentials' => array_filter(['api_key' => $validated['api_key'] ?? null]),
            'settings_json' => array_filter(['sender' => $validated['sender'] ?? null]),
        ]);

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return back()->with('status', __('sms.private_created'));
    }

    public function update(Request $request, SmsGateway $smsGateway, SmsGatewayService $gateways): RedirectResponse
    {
        abort_unless($smsGateway->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80'],
            'status' => ['required', 'in:enabled,disabled'],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
        ]);

        $smsGateway->update([
            'name' => $validated['name'],
            'provider_code' => $validated['provider_code'],
            'status' => $validated['status'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'encrypted_credentials' => array_filter(['api_key' => $validated['api_key'] ?? null]),
            'settings_json' => array_filter(['sender' => $validated['sender'] ?? null]),
        ]);

        if ($smsGateway->is_default) {
            $gateways->markDefault($smsGateway);
        }

        return back()->with('status', __('sms.private_updated'));
    }
}
