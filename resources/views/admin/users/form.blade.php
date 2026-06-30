@php
    $formKey = $formKey ?? ($dashboardUser ? 'edit_user' : 'create_user');
    $selectedTenantIds = collect(old('tenant_ids', $dashboardUser?->tenants->pluck('id')->all() ?? ($tenant ? [$tenant->id] : [])))
        ->map(fn ($tenantId) => (int) $tenantId)
        ->all();
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
@if($returnToIndex ?? false)
    <input type="hidden" name="return_to_index" value="1">
@endif

<x-admin.validation-summary :for="$formKey" class="mb-4" />

<div class="grid gap-4 md:grid-cols-2">
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.name') }}</span>
        <input name="name" value="{{ old('name', $dashboardUser?->name) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.users.email') }}</span>
        <input name="email" value="{{ old('email', $dashboardUser?->email) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.mobile') }}</span>
        <input name="phone_normalized" value="{{ old('phone_normalized', $dashboardUser?->phone_normalized) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.role') }}</span>
        <select name="role" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach($roles as $role)
                <option value="{{ $role->code }}" @selected(old('role', $dashboardUser?->role ?? 'viewer') === $role->code)>{{ __('system.roles.'.$role->code) }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.status') }}</span>
        <select name="status" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach(['active', 'inactive', 'blocked', 'suspended', 'disabled'] as $status)
                <option value="{{ $status }}" @selected(old('status', $dashboardUser?->status ?? 'active') === $status)>{{ __('system.statuses.'.$status) }}</option>
            @endforeach
        </select>
    </label>
</div>

<fieldset class="mt-6 rounded-lg border border-zinc-200 p-4">
    <legend class="px-2 text-sm font-bold text-zinc-700">{{ __('system.users.clinic_access') }}</legend>
    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse($tenants as $tenantOption)
            @php
                $pivot = $dashboardUser?->tenants->firstWhere('id', $tenantOption->id)?->pivot;
                $tenantRole = old("tenant_roles.$tenantOption->id", $pivot?->role ?? 'viewer');
                $tenantStatus = old("tenant_statuses.$tenantOption->id", $pivot?->status ?? 'active');
            @endphp
            <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                <label class="flex items-center gap-2 text-sm font-bold text-zinc-700">
                    <input type="checkbox" name="tenant_ids[]" value="{{ $tenantOption->id }}" @checked(in_array($tenantOption->id, $selectedTenantIds, true))>
                    <span>{{ $tenantOption->name }}</span>
                </label>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <select name="tenant_roles[{{ $tenantOption->id }}]" class="h-10 rounded-lg border border-zinc-200 bg-white px-2 text-xs font-bold">
                        @foreach($roles->where('code', '!=', 'super_admin') as $role)
                            <option value="{{ $role->code }}" @selected($tenantRole === $role->code)>{{ __('system.roles.'.$role->code) }}</option>
                        @endforeach
                    </select>
                    <select name="tenant_statuses[{{ $tenantOption->id }}]" class="h-10 rounded-lg border border-zinc-200 bg-white px-2 text-xs font-bold">
                        @foreach(['active', 'inactive'] as $tenantStatusOption)
                            <option value="{{ $tenantStatusOption }}" @selected($tenantStatus === $tenantStatusOption)>{{ __('system.statuses.'.$tenantStatusOption) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @empty
            <p class="text-sm font-bold text-zinc-500">{{ __('system.users.no_clinics') }}</p>
        @endforelse
    </div>
</fieldset>

<div class="mt-6 flex flex-wrap items-center gap-2">
    <x-admin.button type="submit" size="md-wide" icon="users">
        <span>{{ __('system.save') }}</span>
    </x-admin.button>
    <x-admin.button :href="route('admin.users.index')" variant="secondary" size="compact-md">{{ __('messages.cancel') }}</x-admin.button>
</div>
