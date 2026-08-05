@php
    // @since 2.1.2 Optionally exposes safe system-only diagnostics from model usage rows.
    $showClinic = $showClinic ?? false;
    $showModel = $showModel ?? false;
    $showDiagnostics = $showDiagnostics ?? false;
    $columnCount = 6 + ($showClinic ? 1 : 0) + ($showModel ? 1 : 0);
@endphp

<x-admin.card>
    <h3 class="text-base font-bold">{{ __('ai_models.usage_logs') }}</h3>

    <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
        <table class="w-full min-w-5xl text-right text-sm">
            <thead class="bg-zinc-50 text-xs text-zinc-500">
                <tr>
                    <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                    @if($showClinic)
                        <th class="px-4 py-3">{{ __('system.clinic') }}</th>
                    @endif
                    @if($showModel)
                        <th class="px-4 py-3">{{ __('ai_models.model') }}</th>
                    @endif
                    <th class="px-4 py-3">{{ __('ai_models.usage_type') }}</th>
                    <th class="px-4 py-3">{{ __('leads.fields.phone') }}</th>
                    <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                    <th class="px-4 py-3">{{ __('ai_models.credit_used') }}</th>
                    <th class="px-4 py-3">{{ __('messages.created_at') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse($usageLogs as $log)
                    @php
                        $usageLead = $log->lead && (int) $log->lead->tenant_id === (int) $log->clinic_id
                            ? $log->lead
                            : null;
                        $canViewLead = $usageLead && auth()->user()?->can('view', $usageLead);
                        $canViewDiagnostics = $showDiagnostics
                            && auth()->user()?->isSystemScoped()
                            && $log->aiModelAccess
                            && auth()->user()?->can('view', $log->aiModelAccess);
                    @endphp
                    <tr class="transition hover:bg-zinc-50/80">
                        <td class="px-4 py-3 align-top">
                            @if($canViewLead || $canViewDiagnostics)
                                <x-admin.table.action-menu>
                                    @if($canViewDiagnostics)
                                        <x-admin.table.action-item :href="route('admin.ai-models.usage-logs.show', [$log->aiModelAccess, $log])" icon="chart">
                                            {{ __('ai_models.usage_diagnostics_action') }}
                                        </x-admin.table.action-item>
                                    @endif
                                    @if($canViewLead)
                                        <x-admin.table.action-item :href="route('admin.leads.show', $usageLead)" icon="eye">
                                            {{ __('leads.details') }}
                                        </x-admin.table.action-item>
                                    @endif
                                </x-admin.table.action-menu>
                            @endif
                        </td>
                        @if($showClinic)
                            <td class="px-4 py-3 font-bold">{{ $log->clinic?->name ?? __('messages.none') }}</td>
                        @endif
                        @if($showModel)
                            <td class="px-4 py-3 font-bold">{{ $log->aiModelAccess?->name ?? __('messages.none') }}</td>
                        @endif
                        <td class="px-4 py-3">{{ $resolver->usageCategoryLabel($log->usage_category_key) }}</td>
                        <td class="px-4 py-3">
                            @if($canViewLead)
                                <a class="font-bold text-teal-700 hover:text-teal-900" dir="ltr" href="{{ route('admin.leads.show', $usageLead) }}">
                                    {{ $usageLead->phone_normalized }}
                                </a>
                            @else
                                <span>{{ __('messages.none') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <x-admin.badge :tone="$log->status === 'completed' ? 'emerald' : ($log->status === 'failed' ? 'rose' : 'zinc')">
                                {{ __('ai_models.statuses.'.$log->status) }}
                            </x-admin.badge>
                        </td>
                        <td class="px-4 py-3" dir="ltr">{{ number_format($log->credit_consumed) }}</td>
                        <td class="px-4 py-3 text-zinc-500"><x-jalali-date-time :value="$log->created_at" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="px-4 py-12">
                            <x-admin.empty-state icon="chart">{{ __('ai_models.empty.usage_logs') }}</x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.mobile-list>
        @forelse($usageLogs as $log)
            @php
                $usageLead = $log->lead && (int) $log->lead->tenant_id === (int) $log->clinic_id
                    ? $log->lead
                    : null;
                $canViewLead = $usageLead && auth()->user()?->can('view', $usageLead);
                $canViewDiagnostics = $showDiagnostics
                    && auth()->user()?->isSystemScoped()
                    && $log->aiModelAccess
                    && auth()->user()?->can('view', $log->aiModelAccess);
            @endphp
            <x-admin.mobile-list.card>
                <div class="admin-mobile-list-card-header">
                    <div class="min-w-0">
                        @if($canViewLead)
                            <a class="admin-mobile-list-card-title hover:text-teal-700" dir="ltr" href="{{ route('admin.leads.show', $usageLead) }}">
                                {{ $usageLead->phone_normalized }}
                            </a>
                        @else
                            <p class="admin-mobile-list-card-title">{{ __('messages.none') }}</p>
                        @endif
                        <p class="admin-mobile-list-card-subtitle"><x-jalali-date-time :value="$log->created_at" /></p>
                    </div>
                    <x-admin.badge :tone="$log->status === 'completed' ? 'emerald' : ($log->status === 'failed' ? 'rose' : 'zinc')">
                        {{ __('ai_models.statuses.'.$log->status) }}
                    </x-admin.badge>
                </div>

                <dl class="admin-mobile-list-fields">
                    @if($showClinic)
                        <x-admin.mobile-list.field :label="__('system.clinic')">{{ $log->clinic?->name ?? __('messages.none') }}</x-admin.mobile-list.field>
                    @endif
                    @if($showModel)
                        <x-admin.mobile-list.field :label="__('ai_models.model')">{{ $log->aiModelAccess?->name ?? __('messages.none') }}</x-admin.mobile-list.field>
                    @endif
                    <x-admin.mobile-list.field :label="__('ai_models.usage_type')">{{ $resolver->usageCategoryLabel($log->usage_category_key) }}</x-admin.mobile-list.field>
                    <x-admin.mobile-list.field :label="__('ai_models.credit_used')">
                        <span dir="ltr">{{ number_format($log->credit_consumed) }}</span>
                    </x-admin.mobile-list.field>
                </dl>

                @if($canViewDiagnostics)
                    <div class="mt-4">
                        <x-admin.button :href="route('admin.ai-models.usage-logs.show', [$log->aiModelAccess, $log])" variant="secondary" size="compact-sm" icon="chart">
                            {{ __('ai_models.usage_diagnostics_action') }}
                        </x-admin.button>
                    </div>
                @endif
            </x-admin.mobile-list.card>
        @empty
            <x-admin.mobile-list.card>
                <x-admin.empty-state icon="chart">{{ __('ai_models.empty.usage_logs') }}</x-admin.empty-state>
            </x-admin.mobile-list.card>
        @endforelse
    </x-admin.mobile-list>

    <x-admin.table.pagination-footer :paginator="$usageLogs" />
</x-admin.card>
