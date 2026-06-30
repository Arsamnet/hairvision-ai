<?php

namespace App\Services\Admin;

use App\Models\SmsGateway;
use App\Models\User;
use App\Services\Sms\SmsGatewayService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Builds SMS gateway queries and safe credential mutations.
 *
 * @since 1.0.0
 */
class SmsGatewayManagementService
{
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

    public function paginatePrivate(Request $request, User $user): LengthAwarePaginator
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'provider_code', 'status'],
            'created_at'
        );

        return $this->privateQuery($request, $user, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
    }

    public function systemQuery(Request $request, PaginationParameters $pagination): Builder
    {
        $query = SmsGateway::query()->with('user');
        SearchFilter::apply($query, $pagination->search, ['name', 'provider_code'], [
            'user' => ['name'],
        ]);

        $query
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('owner_scope'), fn (Builder $query) => $query->where('owner_scope', $request->string('owner_scope')->toString()))
            ->when($request->filled('provider_code'), fn (Builder $query) => $query->where('provider_code', $request->string('provider_code')->toString()));

        DateRangeFilter::apply($query, $request);

        return $query
            ->orderBy($pagination->sort, $pagination->direction);
    }

    public function privateQuery(Request $request, User $user, PaginationParameters $pagination): Builder
    {
        $query = SmsGateway::query()
            ->where('owner_scope', 'user')
            ->where('user_id', $user->id);
        SearchFilter::apply($query, $pagination->search, ['name', 'provider_code']);

        $query
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('provider_code'), fn (Builder $query) => $query->where('provider_code', $request->string('provider_code')->toString()));

        DateRangeFilter::apply($query, $request);

        return $query
            ->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSystem(array $data, SmsGatewayService $gateways): SmsGateway
    {
        $gateway = SmsGateway::create($this->attributes($data, null, true));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSystem(SmsGateway $gateway, array $data, SmsGatewayService $gateways): SmsGateway
    {
        $gateway->update($this->attributes($data, $gateway, true));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return $gateway->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPrivate(User $user, array $data, SmsGatewayService $gateways): SmsGateway
    {
        $gateway = SmsGateway::create($this->attributes($data + [
            'owner_scope' => 'user',
            'user_id' => $user->id,
        ], null, true));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePrivate(SmsGateway $gateway, array $data, SmsGatewayService $gateways): SmsGateway
    {
        $gateway->update($this->attributes($data + [
            'owner_scope' => 'user',
            'user_id' => $gateway->user_id,
        ], $gateway, false));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return $gateway->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?SmsGateway $gateway, bool $includeOwner): array
    {
        $credentials = $gateway?->encrypted_credentials ?? [];
        $settings = $gateway?->settings_json ?? [];

        if (filled($data['api_key'] ?? null)) {
            $credentials['api_key'] = $data['api_key'];
        }

        if (array_key_exists('sender', $data)) {
            $settings['sender'] = $data['sender'] ?: null;
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
        }

        return $attributes;
    }
}
