{{-- @since 2.1.0 Introduces the shared unified lead-status badge. --}}
@props(['status'])

<x-admin.badge :tone="$status === 'canceled' ? 'rose' : 'zinc'" data-lead-status="{{ $status }}">
    {{ \App\Support\StateCatalog::label('leads.statuses', (string) $status) }}
</x-admin.badge>
