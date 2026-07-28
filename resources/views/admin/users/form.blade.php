@php
    $formKey = $formKey ?? ($dashboardUser ? 'edit_user' : 'create_user');
    $clinicRoles = $clinicRoles ?? $roles->where('scope', 'clinic');
    $systemRoles = $systemRoles ?? $roles->where('scope', 'platform');
    $isSystemPanel = auth()->user()?->isSuperAdmin() && blank($tenant ?? null);
    $selectedScope = $isSystemPanel ? old('access_scope', $dashboardUser?->access_scope ?? 'clinic') : 'clinic';
    $selectedTenantId = (int) old('tenant_id', $dashboardUser?->tenants->first()?->id ?? ($tenant?->id ?? 0));
    $selectedRole = old('role', $dashboardUser?->role ?? 'viewer');
    $nameParts = preg_split('/\s+/u', trim((string) old('name', $dashboardUser?->name ?? '')), 2);
    $firstName = old('first_name', $nameParts[0] ?? '');
    $lastName = old('last_name', $nameParts[1] ?? '');
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">
<input type="hidden" name="name" data-user-full-name value="{{ old('name', $dashboardUser?->name) }}">
@unless($isSystemPanel)
    <input type="hidden" name="access_scope" value="clinic">
@endunless
@if($returnToIndex ?? false)
    <input type="hidden" name="return_to_index" value="1">
@endif

<x-admin.validation-summary :for="$formKey" class="mb-4" />

<div class="grid gap-4 md:grid-cols-2">
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.name') }}</span>
        <input name="first_name" value="{{ $firstName }}" data-user-first-name class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.last_name') }}</span>
        <input name="last_name" value="{{ $lastName }}" data-user-last-name class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.mobile') }}</span>
        <input name="phone_normalized" value="{{ old('phone_normalized', $dashboardUser?->phone_normalized) }}" inputmode="numeric" data-digit-input class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.users.email') }}</span>
        <input name="email" value="{{ old('email', $dashboardUser?->email) }}" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" dir="ltr">
    </label>
    @if($isSystemPanel)
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.access_scope') }}</span>
        <select name="access_scope" data-user-access-scope class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach(\App\Support\StateCatalog::options('users.access_scopes') as $scope => $label)
                <option value="{{ $scope }}" @selected($selectedScope === $scope)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    @endif
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.role') }}</span>
        <select name="role" data-user-role class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach($systemRoles as $role)
                @php
                    $translationKey = 'system.roles.'.$role->code;
                    $roleLabel = __($translationKey) === $translationKey ? $role->name : __($translationKey);
                @endphp
                <option value="{{ $role->code }}" data-scope="system" @selected($selectedRole === $role->code)>{{ $roleLabel }} — {{ $role->code }}</option>
            @endforeach
            @foreach($clinicRoles as $role)
                @php
                    $translationKey = 'system.roles.'.$role->code;
                    $roleLabel = __($translationKey) === $translationKey ? $role->name : __($translationKey);
                @endphp
                <option value="{{ $role->code }}" data-scope="clinic" @selected($selectedRole === $role->code)>{{ $roleLabel }} — {{ $role->code }}</option>
            @endforeach
        </select>
    </label>
    @if($tenants->isNotEmpty())
        <label class="block" data-user-clinic-field>
            <span class="text-sm font-bold text-zinc-600">{{ __('system.clinic') }}</span>
            <select name="tenant_id" data-user-tenant class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                @foreach($tenants as $tenantOption)
                    <option value="{{ $tenantOption->id }}" @selected($selectedTenantId === $tenantOption->id)>{{ $tenantOption->name }}</option>
                @endforeach
            </select>
        </label>
    @else
        <div class="rounded-lg bg-zinc-50 p-3 text-sm font-bold text-zinc-500" data-user-clinic-field>{{ __('system.users.no_clinics') }}</div>
    @endif
    <label class="block">
        <span class="text-sm font-bold text-zinc-600">{{ __('system.status') }}</span>
        <select name="status" class="mt-2 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
            @foreach(\App\Support\StateCatalog::options('users.statuses') as $status => $label)
                <option value="{{ $status }}" @selected(old('status', $dashboardUser?->status ?? 'active') === $status)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>

<div class="mt-6 flex flex-wrap items-center gap-2">
    <x-admin.button type="submit" size="md-wide" icon="users">
        <span>{{ __('system.save') }}</span>
    </x-admin.button>
    <x-admin.button :href="route('admin.users.index')" variant="secondary" size="compact-md">{{ __('messages.cancel') }}</x-admin.button>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form').forEach((form) => {
                const scopeSelect = form.querySelector('[data-user-access-scope]');
                const roleSelect = form?.querySelector('[data-user-role]');
                const clinicField = form?.querySelector('[data-user-clinic-field]');
                const fullName = form?.querySelector('[data-user-full-name]');
                const firstName = form?.querySelector('[data-user-first-name]');
                const lastName = form?.querySelector('[data-user-last-name]');

                if (!form || !roleSelect) {
                    return;
                }

                const syncName = () => {
                    if (fullName && firstName && lastName) {
                        fullName.value = `${firstName.value.trim()} ${lastName.value.trim()}`.trim();
                    }
                };

                const sync = () => {
                    const scope = scopeSelect?.value || 'clinic';
                    let firstVisible = null;

                    Array.from(roleSelect.options).forEach((option) => {
                        const matches = option.dataset.scope === scope;
                        option.hidden = !matches;
                        option.disabled = !matches;

                        if (matches && firstVisible === null) {
                            firstVisible = option;
                        }
                    });

                    if (roleSelect.selectedOptions[0]?.dataset.scope !== scope && firstVisible) {
                        roleSelect.value = firstVisible.value;
                    }

                    if (clinicField) {
                        clinicField.classList.toggle('hidden', scope !== 'clinic');
                    }
                };

                scopeSelect?.addEventListener('change', sync);
                roleSelect.addEventListener('change', sync);
                firstName?.addEventListener('input', syncName);
                lastName?.addEventListener('input', syncName);
                form.addEventListener('submit', syncName);
                syncName();
                sync();
            });
        });
    </script>
@endonce
