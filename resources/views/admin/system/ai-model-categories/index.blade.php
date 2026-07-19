@extends('admin.layout')

@section('title', __('ai_models.categories.title'))
@section('page_title', __('ai_models.categories.title'))

@php
    $categoryStatuses = [
        'active' => __('ai_models.statuses.active'),
        'inactive' => __('ai_models.statuses.inactive'),
        'archived' => __('ai_models.statuses.archived'),
    ];
@endphp

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('ai_models.categories.title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('ai_models.categories.subtitle') }}</p>
            </div>

            @if(auth()->user()?->hasPermission('ai_model_categories.create'))
                <x-admin.modal id="create-ai-model-category-modal" :label="__('ai_models.categories.create')" :title="__('ai_models.categories.create')" icon="category" :open="$errors->any() && old('_form') === 'create_category'">
                    <form method="POST" action="{{ route('admin.system.ai-model-categories.store') }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_form" value="create_category">
                        <x-admin.validation-summary for="create_category" class="md:col-span-2" />
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('ai_models.categories.code') }}</span>
                            <input name="code" value="{{ old('code') }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.example_code') }}">
                        </label>
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('system.name') }}</span>
                            <input name="name" value="{{ old('name') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.name_placeholder') }}">
                        </label>
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('ai_models.categories.sort_order') }}</span>
                            <input name="sort_order" value="{{ old('sort_order', 100) }}" type="number" min="0" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.sort_placeholder') }}">
                        </label>
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('ai_models.categories.icon_code') }}</span>
                            <input name="icon" value="{{ old('icon') }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.icon_placeholder') }}">
                        </label>
                        <label class="grid gap-1 text-sm font-bold md:col-span-2">
                            <span>{{ __('ai_models.categories.card_image') }}</span>
                            <input name="card_image" type="file" accept="image/png,image/jpeg,image/webp" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none file:ml-3 file:rounded-md file:border-0 file:bg-teal-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-teal-700 focus:border-teal-500">
                            <span class="text-xs font-normal leading-6 text-zinc-500">{{ __('ai_models.categories.card_image_hint') }}</span>
                        </label>
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('ai_models.status') }}</span>
                            <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                                @foreach($categoryStatuses as $status => $label)
                                    <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-bold md:col-span-2">
                            <span>{{ __('system.description') }}</span>
                            <textarea name="description" rows="3" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.description_placeholder') }}">{{ old('description') }}</textarea>
                        </label>
                        <x-admin.button type="submit" icon="check" class="md:col-span-2">
                            <span>{{ __('messages.save') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endif
        </div>

        <x-admin.list-filters
            class="mb-5 mt-5"
            preset-key="admin.system.ai-model-categories.index"
            :search-placeholder="__('messages.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('ai_models.status'), 'type' => 'select', 'placeholder' => __('ai_models.all_statuses'), 'options' => $categoryStatuses],
            ]"
            :sorts="[
                'sort_order' => __('ai_models.categories.sort_order'),
                'created_at' => __('messages.created_at'),
                'code' => __('ai_models.categories.code'),
                'name' => __('system.name'),
                'status' => __('ai_models.status'),
            ]"
        />

        <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-[58rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('system.name')" /></th>
                        <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                        <th class="px-4 py-3">{{ __('ai_models.categories.outputs') }}</th>
                        <th class="px-4 py-3">{{ __('ai_models.categories.models_count') }}</th>
                        <th class="px-4 py-3">{{ __('ai_models.categories.usage_references') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="sort_order" :label="__('ai_models.categories.sort_order')" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($categories as $category)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    <x-admin.table.action-item :href="route('admin.system.ai-model-categories.show', $category)" icon="eye">{{ __('messages.view') }}</x-admin.table.action-item>
                                    @if(auth()->user()?->hasPermission('ai_model_categories.update'))
                                        <label for="edit-category-{{ $category->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="edit-category-{{ $category->id }}">
                                            <x-admin.icon name="edit" class="h-4 w-4" />
                                            <span>{{ __('messages.edit') }}</span>
                                        </label>
                                        @if($category->status !== 'archived')
                                            <x-admin.table.action-item
                                                :action="route('admin.system.ai-model-categories.toggle', $category)"
                                                method="PATCH"
                                                :icon="$category->status === 'active' ? 'x' : 'check'"
                                                :confirm="$category->status === 'active' ? __('ai_models.categories.confirm_deactivate') : null"
                                            >
                                                {{ $category->status === 'active' ? __('ai_models.categories.deactivate') : __('ai_models.categories.activate') }}
                                            </x-admin.table.action-item>
                                        @endif
                                    @endif
                                    @if($category->can_archive_category && auth()->user()?->hasPermission('ai_model_categories.update'))
                                        <x-admin.table.action-item :action="route('admin.system.ai-model-categories.archive', $category)" method="PATCH" icon="archive" :confirm="__('ai_models.categories.confirm_archive')">{{ __('ai_models.categories.archive') }}</x-admin.table.action-item>
                                    @elseif($category->can_delete_category && auth()->user()?->hasPermission('ai_model_categories.delete'))
                                        <x-admin.table.action-item :action="route('admin.system.ai-model-categories.destroy', $category)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                                    @endif
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    @if($category->cardImageUrl())
                                        <img src="{{ $category->cardImageUrl() }}" alt="" class="mt-0.5 h-9 w-9 shrink-0 rounded-lg bg-teal-50 object-contain p-1.5 ring-1 ring-teal-100">
                                    @else
                                        <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                                            <x-admin.category-icon :category="$category" class="h-4 w-4" />
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="font-bold text-zinc-950">{{ $category->name }}</p>
                                        <p class="mt-1 text-xs font-bold text-zinc-500" dir="ltr">{{ $category->code }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$category->status === 'active' ? 'emerald' : ($category->status === 'archived' ? 'amber' : 'zinc')">
                                    {{ __('ai_models.statuses.'.$category->status) }}
                                </x-admin.badge>
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($category->outputs_count) }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($category->total_models_count) }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($category->usage_references_count) }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($category->sort_order) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12">
                                <x-admin.empty-state icon="category">{{ __('messages.none') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($categories as $category)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div class="flex min-w-0 items-start gap-3">
                            @if($category->cardImageUrl())
                                <img src="{{ $category->cardImageUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-lg bg-teal-50 object-contain p-1.5 ring-1 ring-teal-100">
                            @else
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                                    <x-admin.category-icon :category="$category" class="h-4 w-4" />
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $category->name }}</p>
                                <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $category->code }}</p>
                            </div>
                        </div>
                        <x-admin.table.action-menu>
                            <x-admin.table.action-item :href="route('admin.system.ai-model-categories.show', $category)" icon="eye">{{ __('messages.view') }}</x-admin.table.action-item>
                            @if(auth()->user()?->hasPermission('ai_model_categories.update'))
                                <label for="edit-category-{{ $category->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="edit-category-{{ $category->id }}">
                                    <x-admin.icon name="edit" class="h-4 w-4" />
                                    <span>{{ __('messages.edit') }}</span>
                                </label>
                                @if($category->status !== 'archived')
                                    <x-admin.table.action-item
                                        :action="route('admin.system.ai-model-categories.toggle', $category)"
                                        method="PATCH"
                                        :icon="$category->status === 'active' ? 'x' : 'check'"
                                        :confirm="$category->status === 'active' ? __('ai_models.categories.confirm_deactivate') : null"
                                    >
                                        {{ $category->status === 'active' ? __('ai_models.categories.deactivate') : __('ai_models.categories.activate') }}
                                    </x-admin.table.action-item>
                                @endif
                            @endif
                            @if($category->can_archive_category && auth()->user()?->hasPermission('ai_model_categories.update'))
                                <x-admin.table.action-item :action="route('admin.system.ai-model-categories.archive', $category)" method="PATCH" icon="archive" :confirm="__('ai_models.categories.confirm_archive')">{{ __('ai_models.categories.archive') }}</x-admin.table.action-item>
                            @elseif($category->can_delete_category && auth()->user()?->hasPermission('ai_model_categories.delete'))
                                <x-admin.table.action-item :action="route('admin.system.ai-model-categories.destroy', $category)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                            @endif
                        </x-admin.table.action-menu>
                    </div>
                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('ai_models.status')">
                            <x-admin.badge :tone="$category->status === 'active' ? 'emerald' : ($category->status === 'archived' ? 'amber' : 'zinc')">
                                {{ __('ai_models.statuses.'.$category->status) }}
                            </x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('ai_models.categories.outputs')">{{ number_format($category->outputs_count) }}</x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('ai_models.categories.models_count')">{{ number_format($category->total_models_count) }}</x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('ai_models.categories.usage_references')">{{ number_format($category->usage_references_count) }}</x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="category">{{ __('messages.none') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        @foreach($categories as $category)
            <x-admin.modal id="edit-category-{{ $category->id }}" :label="__('messages.edit')" :title="$category->name" icon="edit" :trigger="false" :open="$errors->any() && old('_form') === 'edit_category_'.$category->id">
                <form method="POST" action="{{ route('admin.system.ai-model-categories.update', $category) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="edit_category_{{ $category->id }}">
                    <x-admin.validation-summary for="edit_category_{{ $category->id }}" class="md:col-span-2" />
                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('system.name') }}</span>
                        <input name="name" value="{{ old('name', $category->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.name_placeholder') }}">
                    </label>
                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('ai_models.status') }}</span>
                        <select name="status" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach($categoryStatuses as $status => $label)
                                <option value="{{ $status }}" @selected(old('status', $category->status) === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('ai_models.categories.sort_order') }}</span>
                        <input name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" type="number" min="0" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.sort_placeholder') }}">
                    </label>
                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('ai_models.categories.icon_code') }}</span>
                        <input name="icon" value="{{ old('icon', $category->icon) }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.icon_placeholder') }}">
                    </label>
                    <label class="grid gap-1 text-sm font-bold md:col-span-2">
                        <span>{{ __('ai_models.categories.card_image') }}</span>
                        @if($category->cardImageUrl())
                            <img src="{{ $category->cardImageUrl() }}" alt="" class="h-14 w-14 rounded-xl bg-teal-50 object-contain p-2 ring-1 ring-teal-100">
                        @endif
                        <input name="card_image" type="file" accept="image/png,image/jpeg,image/webp" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none file:ml-3 file:rounded-md file:border-0 file:bg-teal-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-teal-700 focus:border-teal-500">
                        <span class="text-xs font-normal leading-6 text-zinc-500">{{ __('ai_models.categories.card_image_hint') }}</span>
                    </label>
                    <label class="grid gap-1 text-sm font-bold md:col-span-2">
                        <span>{{ __('system.description') }}</span>
                        <textarea name="description" rows="4" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.description_placeholder') }}">{{ old('description', $category->description) }}</textarea>
                    </label>
                    <x-admin.button type="submit" icon="check" class="md:col-span-2">
                        <span>{{ __('messages.save') }}</span>
                    </x-admin.button>
                </form>
            </x-admin.modal>
        @endforeach

        <x-admin.table.pagination-footer :paginator="$categories" class="mt-5" />
    </x-admin.card>
@endsection
