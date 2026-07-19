@extends('admin.layout')

@section('title', __('ai_models.assignment_title'))
@section('page_title', __('ai_models.assignment_title'))

@section('content')
    @php
        $assignedAccessIds = $assignedAccesses->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hasAddableAccess = $availableAccesses->contains(fn ($access): bool => ! in_array((int) $access->id, $assignedAccessIds, true));
    @endphp

    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $clinic->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ai_models.assignment_title') }}</p>
                </div>
                <x-admin.button :href="route('admin.ai-models.index')" variant="secondary" size="sm" icon="sparkles">
                    <span>{{ __('ai_models.title') }}</span>
                </x-admin.button>
            </div>

            @if($availableAccesses->isNotEmpty())
                <div class="mt-5">
                    <x-admin.modal
                        id="assign-clinic-ai-model-modal"
                        :label="__('ai_models.assign')"
                        :title="__('ai_models.assign')"
                        icon="plus"
                        :open="$errors->any() && old('_form') === 'assign_clinic_ai_model_'.$clinic->id"
                    >
                        <form method="POST" action="{{ route('admin.clinics.ai-models.store', $clinic) }}" class="grid gap-3">
                            @csrf
                            <input type="hidden" name="_form" value="assign_clinic_ai_model_{{ $clinic->id }}">
                            <x-admin.validation-summary for="assign_clinic_ai_model_{{ $clinic->id }}" />
                            <label class="grid gap-1 text-sm font-bold">
                                <span>{{ __('ai_models.available_accesses') }}</span>
                                <select name="ai_model_access_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500 disabled:bg-zinc-100 disabled:text-zinc-400" @disabled(! $hasAddableAccess)>
                                    <option value="" disabled selected>{{ __('ai_models.select_access') }}</option>
                                    @foreach($availableAccesses as $access)
                                        @php($isAssigned = in_array((int) $access->id, $assignedAccessIds, true))
                                        <option value="{{ $access->id }}" @disabled($isAssigned)>
                                            {{ $access->name }} - {{ $resolver->usageCategoryLabel($access->usage_category_key) }} @if($isAssigned)({{ __('ai_models.already_assigned') }})@endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
                                <input type="checkbox" name="is_active" value="1" checked @disabled(! $hasAddableAccess)>
                                <span>{{ __('ai_models.statuses.active') }}</span>
                            </label>
                            <x-admin.button type="submit" icon="plus" :disabled="! $hasAddableAccess" class="{{ ! $hasAddableAccess ? 'opacity-50 pointer-events-none' : '' }}">
                                <span>{{ __('ai_models.assign') }}</span>
                            </x-admin.button>
                        </form>
                    </x-admin.modal>
                </div>
            @else
                <div class="mt-5">
                    <x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.available_accesses') }}</x-admin.empty-state>
                </div>
            @endif
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.assigned_clinics') }}</h3>
            <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-216 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3">{{ __('system.name') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.model') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.usage_category') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($assignedAccesses as $access)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        @can('view', $access)
                                            <x-admin.table.action-item :href="route('admin.ai-models.show', $access)" icon="eye">{{ __('ai_models.details') }}</x-admin.table.action-item>
                                        @endcan
                                        @can('update', $access)
                                            <x-admin.table.action-item :href="route('admin.ai-models.edit', $access)" icon="edit">{{ __('ai_models.edit') }}</x-admin.table.action-item>
                                        @endcan
                                        <x-admin.table.action-item
                                            :action="route('admin.clinics.ai-models.update', [$clinic, $access])"
                                            method="PATCH"
                                            icon="check"
                                            :inputs="['ai_model_access_id' => $access->id, 'is_active' => $access->pivot->is_active ? '0' : '1']"
                                        >{{ __('ai_models.toggle_assignment') }}</x-admin.table.action-item>
                                        <x-admin.table.action-item :action="route('admin.clinics.ai-models.destroy', [$clinic, $access])" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('ai_models.unassign') }}</x-admin.table.action-item>
                                    </x-admin.table.action-menu>
                                </td>
                                <td class="px-4 py-3 font-bold">{{ $access->name }}</td>
                                <td class="px-4 py-3">{{ $resolver->modelLabel($access->model_key) }}</td>
                                <td class="px-4 py-3">{{ $resolver->usageCategoryLabel($access->usage_category_key) }}</td>
                                <td class="px-4 py-3"><x-admin.badge :tone="$access->pivot->is_active ? 'emerald' : 'zinc'">{{ $access->pivot->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12">
                                    <x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.assignments') }}</x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($assignedAccesses as $access)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $access->name }}</p>
                                <p class="admin-mobile-list-card-subtitle">{{ $resolver->modelLabel($access->model_key) }}</p>
                            </div>
                            <x-admin.table.action-menu>
                                @can('view', $access)
                                    <x-admin.table.action-item :href="route('admin.ai-models.show', $access)" icon="eye">{{ __('ai_models.details') }}</x-admin.table.action-item>
                                @endcan
                                @can('update', $access)
                                    <x-admin.table.action-item :href="route('admin.ai-models.edit', $access)" icon="edit">{{ __('ai_models.edit') }}</x-admin.table.action-item>
                                @endcan
                                <x-admin.table.action-item
                                    :action="route('admin.clinics.ai-models.update', [$clinic, $access])"
                                    method="PATCH"
                                    icon="check"
                                    :inputs="['ai_model_access_id' => $access->id, 'is_active' => $access->pivot->is_active ? '0' : '1']"
                                >{{ __('ai_models.toggle_assignment') }}</x-admin.table.action-item>
                                <x-admin.table.action-item :action="route('admin.clinics.ai-models.destroy', [$clinic, $access])" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('ai_models.unassign') }}</x-admin.table.action-item>
                            </x-admin.table.action-menu>
                        </div>

                        <dl class="admin-mobile-list-fields">
                            <x-admin.mobile-list.field :label="__('ai_models.usage_category')">
                                {{ $resolver->usageCategoryLabel($access->usage_category_key) }}
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('ai_models.status')">
                                <x-admin.badge :tone="$access->pivot->is_active ? 'emerald' : 'zinc'">{{ $access->pivot->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                            </x-admin.mobile-list.field>
                        </dl>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.assignments') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>
        </x-admin.card>
    </div>
@endsection
