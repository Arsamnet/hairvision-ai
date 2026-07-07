@extends('admin.layout')

@section('title', __('ai_models.title'))
@section('page_title', __('ai_models.title'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('ai_models.title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('ai_models.subtitle') }}</p>
            </div>
            @can('create', App\Models\AiModelAccess::class)
                <x-admin.modal id="create-ai-model-access-modal" :label="__('ai_models.create')" :title="__('ai_models.create')" icon="sparkles" :open="$errors->any() && old('_form') === 'create_ai_model_access'">
                    <form method="POST" action="{{ route('admin.ai-models.store') }}">
                        @csrf
                        @include('admin.ai-models.form', [
                            'access' => null,
                            'models' => $models,
                            'usageCategories' => $usageCategories,
                            'resolver' => $resolver,
                            'formKey' => 'create_ai_model_access',
                            'returnToIndex' => true,
                        ])
                    </form>
                </x-admin.modal>
            @endcan
        </div>

        <x-admin.list-filters
            preset-key="admin.ai-models.index"
            :search-placeholder="__('ai_models.search_placeholder')"
            :filters="[
                ['name' => 'model_key', 'label' => __('ai_models.model'), 'type' => 'select', 'placeholder' => __('ai_models.all_models'), 'options' => collect(array_keys($models))->mapWithKeys(fn ($key) => [$key => $resolver->modelLabel($key)])->all()],
                ['name' => 'usage_category_key', 'label' => __('ai_models.usage_category'), 'type' => 'select', 'placeholder' => __('ai_models.all_categories'), 'options' => collect(array_keys($usageCategories))->mapWithKeys(fn ($key) => [$key => $resolver->usageCategoryLabel($key)])->all()],
                ['name' => 'status', 'label' => __('ai_models.status'), 'type' => 'select', 'placeholder' => __('ai_models.all_statuses'), 'options' => ['active' => __('ai_models.statuses.active'), 'inactive' => __('ai_models.statuses.inactive')]],
                ['name' => 'flag', 'label' => __('ai_models.flags.normal'), 'type' => 'select', 'placeholder' => __('ai_models.all_flags'), 'options' => ['demo' => __('ai_models.flags.demo'), 'protected' => __('ai_models.flags.protected')]],
                ['name' => 'credit_status', 'label' => __('ai_models.remaining_credit'), 'type' => 'select', 'placeholder' => __('ai_models.all_credit_statuses'), 'options' => collect(['available', 'finished', 'unlimited'])->mapWithKeys(fn ($status) => [$status => __('ai_models.credit_statuses.'.$status)])->all()],
            ]"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'name' => __('system.name'),
                'model_key' => __('ai_models.model'),
                'usage_category_key' => __('ai_models.usage_category'),
                'credit_used' => __('ai_models.credit_used'),
                'priority' => __('ai_models.priority'),
                'last_used_at' => __('ai_models.last_used_at'),
            ]"
        />

        <div data-admin-table-container class="mt-5 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-216 text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('system.name')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="model_key" :label="__('ai_models.model')" /></th>
                        <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="credit_used" :label="__('ai_models.credit_used')" /></th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="last_used_at" :label="__('ai_models.last_used_at')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($aiModelAccesses as $access)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @can('view', $access)
                                        <x-admin.table.action-item :href="route('admin.ai-models.show', $access)" icon="eye">{{ __('ai_models.details') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('toggle', $access)
                                        <x-admin.table.action-item :action="route('admin.ai-models.toggle', $access)" method="PATCH" icon="check">{{ __('ai_models.toggle') }}</x-admin.table.action-item>
                                    @endcan
                                    @can('delete', $access)
                                        <x-admin.table.action-item :action="route('admin.ai-models.destroy', $access)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                                    @endcan
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold">{{ $access->name }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if($access->is_demo)
                                        <x-admin.badge tone="teal">{{ __('ai_models.flags.demo') }}</x-admin.badge>
                                    @endif
                                    @if($access->is_protected)
                                        <x-admin.badge tone="amber">{{ __('ai_models.flags.protected') }}</x-admin.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold">{{ $resolver->modelLabel($access->model_key) }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $resolver->usageCategoryLabel($access->usage_category_key) }}</p>
                            </td>
                            <td class="px-4 py-3"><x-admin.badge :tone="$access->is_active ? 'emerald' : 'zinc'">{{ $access->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge></td>
                            <td class="px-4 py-3" dir="ltr">
                                <p>{{ number_format($access->credit_used) }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ $access->remainingCredit() === null ? __('ai_models.unlimited_credit') : number_format($access->remainingCredit()) }}</p>
                            </td>
                            <td class="px-4 py-3 text-zinc-500"><x-jalali-date-time :value="$access->last_used_at" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.accesses') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($aiModelAccesses as $access)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="min-w-0">
                            <p class="admin-mobile-list-card-title">{{ $access->name }}</p>
                            <p class="admin-mobile-list-card-subtitle">{{ $resolver->modelLabel($access->model_key) }} - {{ $resolver->usageCategoryLabel($access->usage_category_key) }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @can('view', $access)
                                <x-admin.table.action-item :href="route('admin.ai-models.show', $access)" icon="eye">{{ __('ai_models.details') }}</x-admin.table.action-item>
                            @endcan
                            @can('toggle', $access)
                                <x-admin.table.action-item :action="route('admin.ai-models.toggle', $access)" method="PATCH" icon="check">{{ __('ai_models.toggle') }}</x-admin.table.action-item>
                            @endcan
                            @can('delete', $access)
                                <x-admin.table.action-item :action="route('admin.ai-models.destroy', $access)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                            @endcan
                        </x-admin.table.action-menu>
                    </div>

                    <div class="flex flex-wrap gap-1">
                        @if($access->is_demo)
                            <x-admin.badge tone="teal">{{ __('ai_models.flags.demo') }}</x-admin.badge>
                        @endif
                        @if($access->is_protected)
                            <x-admin.badge tone="amber">{{ __('ai_models.flags.protected') }}</x-admin.badge>
                        @endif
                    </div>

                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('ai_models.status')">
                            <x-admin.badge :tone="$access->is_active ? 'emerald' : 'zinc'">{{ $access->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('ai_models.credit_used')">
                            <span dir="ltr">{{ number_format($access->credit_used) }} / {{ $access->remainingCredit() === null ? __('ai_models.unlimited_credit') : number_format($access->remainingCredit()) }}</span>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('ai_models.last_used_at')">
                            <x-jalali-date-time :value="$access->last_used_at" />
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="sparkles">{{ __('ai_models.empty.accesses') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>
        <x-admin.table.pagination-footer :paginator="$aiModelAccesses" />
    </x-admin.card>
@endsection
