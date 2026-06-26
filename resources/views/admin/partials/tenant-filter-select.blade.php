@php
    $selectedTenantValue = request('tenant_id', $tenant ? (string) $tenant->id : 'all');
@endphp

@isset($availableTenants)
    @if(auth()->user()?->isSuperAdmin())
        <label class="block">
            <span class="mb-2 block text-xs font-bold text-zinc-500">کلینیک</span>
            <select name="tenant_id" class="admin-select">
                <option value="all" @selected($selectedTenantValue === 'all')>همه کلینیک‌ها</option>
                @foreach($availableTenants->sortBy('name') as $optionTenant)
                    <option value="{{ $optionTenant->id }}" @selected($selectedTenantValue == $optionTenant->id)>{{ $optionTenant->name }}</option>
                @endforeach
            </select>
        </label>
    @endif
@endisset
