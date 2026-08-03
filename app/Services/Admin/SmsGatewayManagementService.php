<?php

namespace App\Services\Admin;

use App\Models\SmsGateway;
use App\Models\Tenant;
use App\Services\Sms\DebugSmsGatewayAvailability;
use App\Services\Sms\SmsGatewayService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use App\Support\StateCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Builds SMS gateway queries and safe credential mutations.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class SmsGatewayManagementService
{
    /**
     * Create the SMS gateway management service.
     *
     * @param  DebugSmsGatewayAvailability  $debugGateway  Runtime debug gateway boundary.
     *
     * @since 2.1.0
     */
    public function __construct(
        private readonly DebugSmsGatewayAvailability $debugGateway,
    ) {}

    /**
     * Execute the paginate system operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return LengthAwarePaginator Paginated result set.
     *
     * @since 1.0.0
     */
    public function paginateSystem(Request $request): LengthAwarePaginator
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'provider_code', 'owner_scope', 'status'],
            'created_at'
        );

        return $this->systemQuery($request, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
    }

    /**
     * Execute the system query operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  PaginationParameters  $pagination  The pagination value.
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function systemQuery(Request $request, PaginationParameters $pagination): Builder
    {
        $query = SmsGateway::query()
            ->with(['user', 'tenant'])
            ->whereIn('owner_scope', StateCatalog::values('sms_gateways.owner_scopes'));
        SearchFilter::apply($query, $pagination->search, ['name', 'provider_code'], [
            'user' => ['name'],
            'tenant' => ['name'],
        ]);

        $this->applyEffectiveStatusFilter($query, $request);

        $query
            ->when($request->filled('owner_scope'), fn (Builder $query) => $query->where('owner_scope', $request->string('owner_scope')->toString()))
            ->when($request->filled('provider_code'), fn (Builder $query) => $query->where('provider_code', $request->string('provider_code')->toString()));

        DateRangeFilter::apply($query, $request);

        if ($pagination->sort === 'status') {
            return $query->orderByRaw(
                "CASE WHEN provider_code = ? THEN ? ELSE status END {$pagination->direction}",
                [
                    DebugSmsGatewayAvailability::PROVIDER_CODE,
                    $this->debugGateway->isEnabled() ? 'enabled' : 'disabled',
                ]
            );
        }

        return $query->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * Execute the clinic settings operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return SmsGateway|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function clinicSettings(Tenant $tenant): ?SmsGateway
    {
        return SmsGateway::query()
            ->where('owner_scope', 'tenant')
            ->where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? SmsGateway::query()
                ->where('owner_scope', 'tenant')
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->first();
    }

    /**
     * Execute the provider options for system operation.
     *
     * @param  SmsGateway|null  $gateway  The gateway value.
     * @return array<string, string>
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function providerOptionsForSystem(?SmsGateway $gateway = null): array
    {
        $options = $this->knownProviderOptions();

        SmsGateway::query()
            ->system()
            ->enabled()
            ->where('provider_code', '!=', DebugSmsGatewayAvailability::PROVIDER_CODE)
            ->orderBy('provider_code')
            ->get(['provider_code', 'name'])
            ->each(function (SmsGateway $systemGateway) use (&$options): void {
                $options[$systemGateway->provider_code] = $this->providerLabel(
                    $systemGateway->provider_code,
                    $systemGateway->name
                );
            });

        if ($gateway
            && $gateway->provider_code !== DebugSmsGatewayAvailability::PROVIDER_CODE
            && ! array_key_exists($gateway->provider_code, $options)) {
            $options[$gateway->provider_code] = $this->providerLabel($gateway->provider_code, $gateway->name);
        }

        return $options;
    }

    /**
     * Execute the provider options for private operation.
     *
     * @param  SmsGateway|null  $gateway  The gateway value.
     * @return array<string, string>
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function providerOptionsForPrivate(?SmsGateway $gateway = null): array
    {
        $options = [];

        SmsGateway::query()
            ->system()
            ->enabled()
            ->where('provider_code', '!=', DebugSmsGatewayAvailability::PROVIDER_CODE)
            ->orderBy('provider_code')
            ->get(['provider_code', 'name'])
            ->each(function (SmsGateway $systemGateway) use (&$options): void {
                $options[$systemGateway->provider_code] = $this->providerLabel(
                    $systemGateway->provider_code,
                    $systemGateway->name
                );
            });

        if ($options === []) {
            $options = $this->knownProviderOptions();
        }

        if ($gateway
            && $gateway->provider_code !== DebugSmsGatewayAvailability::PROVIDER_CODE
            && ! array_key_exists($gateway->provider_code, $options)) {
            $options[$gateway->provider_code] = $this->providerLabel($gateway->provider_code, $gateway->name);
        }

        return $options;
    }

    /**
     * Create system.
     *
     * @param  array<string, mixed>  $data
     * @param  SmsGatewayService  $gateways  The gateways value.
     * @return SmsGateway Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function createSystem(array $data, SmsGatewayService $gateways): SmsGateway
    {
        $this->ensureDebugGatewayIsEnvironmentManaged($data);

        if (($data['owner_scope'] ?? null) === 'tenant') {
            $tenant = Tenant::query()->findOrFail((int) $data['tenant_id']);

            return $this->saveClinicSettings($tenant, $data, $gateways);
        }

        $gateway = SmsGateway::create($this->attributes($data, null, true));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return $gateway;
    }

    /**
     * Execute the update system operation.
     *
     * @param  array<string, mixed>  $data
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  SmsGatewayService  $gateways  The gateways value.
     * @return SmsGateway Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function updateSystem(SmsGateway $gateway, array $data, SmsGatewayService $gateways): SmsGateway
    {
        $this->ensureMutable($gateway);
        $this->ensureDebugGatewayIsEnvironmentManaged($data);

        if (($data['owner_scope'] ?? null) === 'tenant') {
            $tenant = Tenant::query()->findOrFail((int) $data['tenant_id']);
            $existing = $this->clinicSettings($tenant);

            if ($existing && ! $existing->is($gateway)) {
                $existing->delete();
            }
        }

        $gateway->update($this->attributes($data, $gateway, true));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return $gateway->refresh();
    }

    /**
     * Delete a mutable SMS gateway.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 2.0.0
     */
    public function deleteSystem(SmsGateway $gateway): void
    {
        $this->ensureMutable($gateway);

        $gateway->delete();
    }

    /**
     * Persist clinic settings.
     *
     * @param  array<string, mixed>  $data
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  SmsGatewayService  $gateways  The gateways value.
     * @return SmsGateway Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function saveClinicSettings(Tenant $tenant, array $data, SmsGatewayService $gateways): SmsGateway
    {
        $this->ensureDebugGatewayIsEnvironmentManaged($data);

        $gateway = $this->clinicSettings($tenant);

        $attributes = $this->attributes($data + [
            'name' => $data['name'] ?? __('sms.clinic_gateway_name'),
            'provider_code' => $data['provider_code'] ?? 'ippanel',
            'status' => $data['status'] ?? 'disabled',
            'is_default' => true,
            'owner_scope' => 'tenant',
            'tenant_id' => $tenant->id,
        ], $gateway, true);

        if ($gateway) {
            $gateway->update($attributes);
        } else {
            $gateway = SmsGateway::create($attributes);
        }

        return $gateways->markDefault($gateway);
    }

    /**
     * Return localized validation attribute names.
     *
     * @param  array<string, mixed>  $data
     * @param  SmsGateway|null  $gateway  The gateway value.
     * @param  bool  $includeOwner  The include owner value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function attributes(array $data, ?SmsGateway $gateway, bool $includeOwner): array
    {
        $credentials = $gateway?->encrypted_credentials ?? [];
        $settings = $gateway?->settings_json ?? [];

        if (filled($data['api_key'] ?? null)) {
            $credentials['api_key'] = $data['api_key'];
        }

        if (array_key_exists('from_number', $data)) {
            $settings['from_number'] = $data['from_number'] ?: null;
            $settings['sender'] = $data['from_number'] ?: null;
        }

        if (array_key_exists('sender', $data)) {
            $settings['sender'] = $data['sender'] ?: null;
            $settings['from_number'] = $data['sender'] ?: null;
        }

        if (array_key_exists('otp_pattern_code', $data)) {
            $settings['otp_pattern_code'] = $data['otp_pattern_code'] ?: null;
        }

        if (array_key_exists('otp_pattern_variable', $data)) {
            $settings['otp_pattern_variable'] = $data['otp_pattern_variable'] ?: null;
        }

        if (array_key_exists('endpoint_url', $data)) {
            $settings['endpoint_url'] = $data['endpoint_url'] ?: null;
        }

        $attributes = [
            'name' => $data['name'],
            'provider_code' => $data['provider_code'],
            'status' => $data['status'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'encrypted_credentials' => array_filter($credentials),
            'settings_json' => array_filter($settings),
        ];

        if ($includeOwner) {
            $attributes['owner_scope'] = $data['owner_scope'];
            $attributes['user_id'] = ($data['owner_scope'] ?? null) === 'user' ? (int) $data['user_id'] : null;
            $attributes['tenant_id'] = ($data['owner_scope'] ?? null) === 'tenant' ? (int) $data['tenant_id'] : null;
        }

        return $attributes;
    }

    /**
     * Reject mutations of system-owned protected gateways.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 2.0.0
     */
    private function ensureMutable(SmsGateway $gateway): void
    {
        if (! $gateway->is_protected) {
            return;
        }

        throw ValidationException::withMessages([
            'sms_gateway' => __('sms.protected_mutation_denied'),
        ]);
    }

    /**
     * Prevent dashboard mutations from creating or activating the debug provider.
     *
     * @param  array<string, mixed>  $data  Validated gateway values.
     * @return void No value is returned.
     *
     * @throws ValidationException
     *
     * @since 2.1.0
     */
    private function ensureDebugGatewayIsEnvironmentManaged(array $data): void
    {
        if (($data['provider_code'] ?? null) !== DebugSmsGatewayAvailability::PROVIDER_CODE) {
            return;
        }

        throw ValidationException::withMessages([
            'provider_code' => __('sms.debug_gateway_env_only'),
        ]);
    }

    /**
     * Execute the known provider options operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    private function knownProviderOptions(): array
    {
        return [
            'ippanel' => __('sms.providers.ippanel'),
        ];
    }

    /**
     * Apply dashboard status filtering against each gateway's effective runtime status.
     *
     * @param  Builder<SmsGateway>  $query  Gateway list query.
     * @param  Request  $request  Incoming HTTP request.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    private function applyEffectiveStatusFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('status')) {
            return;
        }

        $status = $request->string('status')->toString();

        if ($this->debugGateway->isEnabled()) {
            if ($status === 'enabled') {
                $query->where(function (Builder $query): void {
                    $query
                        ->where('status', 'enabled')
                        ->orWhere('provider_code', DebugSmsGatewayAvailability::PROVIDER_CODE);
                });

                return;
            }

            if ($status === 'disabled') {
                $query
                    ->where('status', 'disabled')
                    ->where('provider_code', '!=', DebugSmsGatewayAvailability::PROVIDER_CODE);

                return;
            }

            $query->where('status', $status);

            return;
        }

        if ($status === 'enabled') {
            $query
                ->where('status', 'enabled')
                ->where('provider_code', '!=', DebugSmsGatewayAvailability::PROVIDER_CODE);

            return;
        }

        if ($status === 'disabled') {
            $query->where(function (Builder $query): void {
                $query
                    ->where('status', 'disabled')
                    ->orWhere('provider_code', DebugSmsGatewayAvailability::PROVIDER_CODE);
            });

            return;
        }

        $query->where('status', $status);
    }

    /**
     * Execute the provider label operation.
     *
     * @param  string  $providerCode  The provider code value.
     * @param  string|null  $gatewayName  The gateway name value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function providerLabel(string $providerCode, ?string $gatewayName = null): string
    {
        $known = $this->knownProviderOptions();

        return $known[$providerCode] ?? $gatewayName ?? $providerCode;
    }
}
