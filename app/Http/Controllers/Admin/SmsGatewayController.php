<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsGateway;
use App\Models\User;
use App\Services\Sms\SmsGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages platform SMS gateways and user-private gateway records.
 *
 * @since 1.0.0
 */
class SmsGatewayController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.system.sms-gateways.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => SmsGateway::query()->with('user')->latest()->paginate(20),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SmsGatewayService $gateways): RedirectResponse
    {
        $validated = $this->validated($request);

        $gateway = SmsGateway::create($validated);

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return back()->with('status', __('sms.created'));
    }

    public function update(Request $request, SmsGateway $smsGateway, SmsGatewayService $gateways): RedirectResponse
    {
        $validated = $this->validated($request);

        $smsGateway->update($validated);

        if ($smsGateway->is_default) {
            $gateways->markDefault($smsGateway);
        }

        return back()->with('status', __('sms.updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80'],
            'owner_scope' => ['required', Rule::in(['system', 'user'])],
            'user_id' => ['nullable', 'required_if:owner_scope,user', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'name' => $validated['name'],
            'provider_code' => $validated['provider_code'],
            'owner_scope' => $validated['owner_scope'],
            'user_id' => $validated['owner_scope'] === 'user' ? (int) $validated['user_id'] : null,
            'status' => $validated['status'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'encrypted_credentials' => array_filter([
                'api_key' => $validated['api_key'] ?? null,
            ]),
            'settings_json' => array_filter([
                'sender' => $validated['sender'] ?? null,
            ]),
        ];
    }
}
