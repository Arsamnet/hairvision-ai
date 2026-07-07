@extends('admin.layout')

@section('title', $aiModelAccess->name)
@section('page_title', __('ai_models.details'))

@section('content')
    @php
        $modelConfig = $resolver->model($aiModelAccess->model_key);
        $requiredCredentials = collect($modelConfig['required_credentials'] ?? []);
        $credentialsConfigured = $requiredCredentials->isEmpty()
            || $requiredCredentials->every(fn (string $field): bool => filled(data_get($aiModelAccess->credentials ?? [], $field)));
    @endphp

    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $aiModelAccess->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ $resolver->modelLabel($aiModelAccess->model_key) }} - {{ $resolver->usageCategoryLabel($aiModelAccess->usage_category_key) }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-admin.badge :tone="$aiModelAccess->is_active ? 'emerald' : 'zinc'">{{ $aiModelAccess->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                        @if($aiModelAccess->is_demo)
                            <x-admin.badge tone="teal">{{ __('ai_models.flags.demo') }}</x-admin.badge>
                        @endif
                        @if($aiModelAccess->is_protected)
                            <x-admin.badge tone="amber">{{ __('ai_models.flags.protected') }}</x-admin.badge>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('toggle', $aiModelAccess)
                        <form method="POST" action="{{ route('admin.ai-models.toggle', $aiModelAccess) }}">
                            @csrf
                            @method('PATCH')
                            <x-admin.button type="submit" variant="secondary" size="sm" icon="check">
                                <span>{{ __('ai_models.toggle') }}</span>
                            </x-admin.button>
                        </form>
                    @endcan
                    @can('delete', $aiModelAccess)
                        <form method="POST" action="{{ route('admin.ai-models.destroy', $aiModelAccess) }}" data-confirm-form data-confirm-message="{{ __('messages.confirm_delete') }}">
                            @csrf
                            @method('DELETE')
                            <x-admin.button type="submit" variant="danger" size="sm" icon="trash">
                                <span>{{ __('messages.delete') }}</span>
                            </x-admin.button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.model_type') }}</p>
                    <p class="mt-1 font-bold">{{ $aiModelAccess->is_demo ? __('ai_models.flags.demo') : __('ai_models.flags.real') }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.credential_status') }}</p>
                    <p class="mt-1 font-bold">
                        @if($requiredCredentials->isEmpty())
                            {{ __('ai_models.credentials_not_required') }}
                        @else
                            {{ $credentialsConfigured ? __('ai_models.credentials_configured') : __('ai_models.credentials_missing') }}
                        @endif
                    </p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.credit_limit') }}</p>
                    <p class="mt-1 font-bold">{{ $aiModelAccess->credit_limit === null ? __('ai_models.unlimited_credit') : number_format($aiModelAccess->credit_limit) }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.credit_used') }}</p>
                    <p class="mt-1 font-bold">{{ number_format($aiModelAccess->credit_used) }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.remaining_credit') }}</p>
                    <p class="mt-1 font-bold">{{ $aiModelAccess->remainingCredit() === null ? __('ai_models.unlimited_credit') : number_format($aiModelAccess->remainingCredit()) }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.last_used_at') }}</p>
                    <p class="mt-1 font-bold"><x-jalali-date-time :value="$aiModelAccess->last_used_at" /></p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.credentials') }}</h3>
            <div class="mt-3 grid gap-2 md:grid-cols-2">
                @forelse($aiModelAccess->maskedCredentials() as $key => $value)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 p-3 text-sm">
                        <span class="font-bold">{{ __('ai_models.'.$key) }}</span>
                        <span dir="ltr">{{ $value }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('messages.none') }}</p>
                @endforelse
            </div>
        </x-admin.card>

        @can('update', $aiModelAccess)
            <x-admin.card>
                <h3 class="text-base font-bold">{{ __('ai_models.edit') }}</h3>
                <form method="POST" action="{{ route('admin.ai-models.update', $aiModelAccess) }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    @include('admin.ai-models.form', [
                        'access' => $aiModelAccess,
                        'models' => $models,
                        'usageCategories' => $usageCategories,
                        'resolver' => $resolver,
                        'formKey' => 'edit_ai_model_access',
                    ])
                </form>
            </x-admin.card>
        @endcan

        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold">{{ __('ai_models.assigned_clinics') }}</h3>
            </div>
            <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-152 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('system.clinic') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                            <th class="px-4 py-3">{{ __('messages.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($aiModelAccess->clinics as $clinic)
                            <tr>
                                <td class="px-4 py-3 font-bold">
                                    <a class="text-teal-700 hover:text-teal-900" href="{{ route('admin.clinics.ai-models.index', $clinic) }}">{{ $clinic->name }}</a>
                                </td>
                                <td class="px-4 py-3"><x-admin.badge :tone="$clinic->pivot->is_active ? 'emerald' : 'zinc'">{{ $clinic->pivot->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge></td>
                                <td class="px-4 py-3 text-zinc-500"><x-jalali-date-time :value="$clinic->pivot->created_at" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12">
                                    <x-admin.empty-state icon="building">{{ __('ai_models.empty.clinics') }}</x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($aiModelAccess->clinics as $clinic)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <a class="admin-mobile-list-card-title hover:text-teal-700" href="{{ route('admin.clinics.ai-models.index', $clinic) }}">{{ $clinic->name }}</a>
                                <p class="admin-mobile-list-card-subtitle"><x-jalali-date-time :value="$clinic->pivot->created_at" /></p>
                            </div>
                            <x-admin.badge :tone="$clinic->pivot->is_active ? 'emerald' : 'zinc'">{{ $clinic->pivot->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                        </div>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="building">{{ __('ai_models.empty.clinics') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.usage_logs') }}</h3>
            <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-208 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('system.clinic') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.credit_used') }}</th>
                            <th class="px-4 py-3">{{ __('messages.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($usageLogs as $log)
                            <tr>
                                <td class="px-4 py-3 font-bold">{{ $log->clinic?->name }}</td>
                                <td class="px-4 py-3"><x-admin.badge :tone="$log->status === 'completed' ? 'emerald' : ($log->status === 'failed' ? 'rose' : 'zinc')">{{ __('ai_models.statuses.'.$log->status) }}</x-admin.badge></td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($log->credit_consumed) }}</td>
                                <td class="px-4 py-3 text-zinc-500"><x-jalali-date-time :value="$log->created_at" /></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12">
                                    <x-admin.empty-state icon="chart">{{ __('ai_models.empty.usage_logs') }}</x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-admin.mobile-list>
                @forelse($usageLogs as $log)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $log->clinic?->name }}</p>
                                <p class="admin-mobile-list-card-subtitle"><x-jalali-date-time :value="$log->created_at" /></p>
                            </div>
                            <x-admin.badge :tone="$log->status === 'completed' ? 'emerald' : ($log->status === 'failed' ? 'rose' : 'zinc')">{{ __('ai_models.statuses.'.$log->status) }}</x-admin.badge>
                        </div>
                        <dl class="admin-mobile-list-fields">
                            <x-admin.mobile-list.field :label="__('ai_models.credit_used')">
                                <span dir="ltr">{{ number_format($log->credit_consumed) }}</span>
                            </x-admin.mobile-list.field>
                        </dl>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="chart">{{ __('ai_models.empty.usage_logs') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>
            <x-admin.table.pagination-footer :paginator="$usageLogs" />
        </x-admin.card>
    </div>
@endsection
