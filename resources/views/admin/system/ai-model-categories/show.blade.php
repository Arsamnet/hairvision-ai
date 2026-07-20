@extends('admin.layout')

@section('title', __('ai_models.categories.view_title', ['name' => $category->name]))
@section('page_title', __('ai_models.categories.view_title', ['name' => $category->name]))

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    @if($category->cardImageUrl())
                        <img src="{{ $category->cardImageUrl() }}" alt="" class="h-11 w-11 shrink-0 rounded-lg bg-teal-50 object-contain p-2 ring-1 ring-teal-100">
                    @else
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-teal-50 text-teal-700 ring-1 ring-teal-100">
                            <x-admin.category-icon :category="$category" />
                        </span>
                    @endif
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold">{{ $category->name }}</h2>
                        <p class="mt-1 text-sm font-bold text-zinc-500" dir="ltr">{{ $category->code }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-admin.button :href="route('admin.system.ai-model-categories.index')" variant="secondary" size="sm" icon="category">
                        <span>{{ __('ai_models.categories.title') }}</span>
                    </x-admin.button>
                </div>
            </div>

            <dl class="mt-5 grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.status') }}</dt>
                    <dd class="mt-1">
                        <x-admin.badge :tone="$category->status === 'active' ? 'emerald' : ($category->status === 'archived' ? 'amber' : 'zinc')">
                            {{ __('ai_models.statuses.'.$category->status) }}
                        </x-admin.badge>
                    </dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.categories.outputs') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($category->outputs_count) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.categories.models_count') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($category->total_models_count) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.categories.usage_references') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($category->usage_references_count) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.categories.sort_order') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($category->sort_order) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('messages.created_at') }}</dt>
                    <dd class="mt-1 font-bold"><x-jalali-date :value="$category->created_at" /></dd>
                </div>
            </dl>

            <div class="mt-4 rounded-lg bg-zinc-50 p-3">
                <p class="text-xs font-bold text-zinc-500">{{ __('system.description') }}</p>
                <p class="mt-1 text-sm leading-7 text-zinc-800">{{ $category->description ?: __('messages.none') }}</p>
            </div>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.categories.usage_references') }}</h3>
            <dl class="mt-4 grid gap-3 md:grid-cols-3">
                @foreach($usage as $key => $count)
                    <div class="rounded-lg bg-zinc-50 p-3">
                        <dt class="text-xs font-bold text-zinc-500" dir="ltr">{{ $key }}</dt>
                        <dd class="mt-1 font-bold" dir="ltr">{{ number_format($count) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-admin.card>

        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold">{{ __('ai_models.categories.outputs') }}</h3>
                @if(auth()->user()?->hasPermission('ai_model_category_outputs.manage'))
                    <x-admin.modal id="show-create-output-{{ $category->id }}" :label="__('ai_models.outputs.create')" :title="__('ai_models.outputs.create')" icon="plus" :open="$errors->any() && old('_form') === 'create_output_'.$category->id">
                        <form method="POST" action="{{ route('admin.system.ai-model-categories.outputs.store', $category) }}" class="grid gap-3 md:grid-cols-2">
                            @csrf
                            <input type="hidden" name="_form" value="create_output_{{ $category->id }}">
                            <x-admin.validation-summary for="create_output_{{ $category->id }}" class="md:col-span-2" />
                            <input name="code" value="{{ old('code') }}" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.outputs.code') }}">
                            <input name="name" value="{{ old('name') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                            <input name="credit_cost" value="{{ old('credit_cost', 1) }}" type="text" inputmode="numeric" data-number-input min="0" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.outputs.credit_cost') }}">
                            <input name="sort_order" value="{{ old('sort_order', 100) }}" type="text" inputmode="numeric" data-number-input min="0" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.sort_placeholder') }}">
                            <textarea name="description" rows="3" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('ai_models.categories.description_placeholder') }}">{{ old('description') }}</textarea>
                            <textarea name="prompt" rows="6" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('ai_models.outputs.prompt_placeholder') }}">{{ old('prompt') }}</textarea>
                            <x-admin.button type="submit" icon="check" class="md:col-span-2">
                                <span>{{ __('messages.save') }}</span>
                            </x-admin.button>
                        </form>
                    </x-admin.modal>
                @endif
            </div>

            <div data-admin-table-container class="mt-4 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-[52rem] text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                            <th class="px-4 py-3">{{ __('system.name') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.status') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.prompt') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.outputs.credit_cost') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.categories.models_count') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.categories.usage_references') }}</th>
                            <th class="px-4 py-3">{{ __('ai_models.categories.sort_order') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($category->outputs as $output)
                            <tr class="transition hover:bg-zinc-50/80">
                                <td class="px-4 py-3 align-top">
                                    <x-admin.table.action-menu>
                                        @if(auth()->user()?->hasPermission('ai_model_category_outputs.manage'))
                                            <label for="show-edit-output-{{ $output->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="show-edit-output-{{ $output->id }}">
                                                <x-admin.icon name="edit" class="h-4 w-4" />
                                                <span>{{ __('messages.edit') }}</span>
                                            </label>
                                            <x-admin.table.action-item :action="route('admin.system.ai-model-categories.outputs.destroy', [$category, $output])" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                                        @endif
                                    </x-admin.table.action-menu>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-bold text-zinc-950">{{ $output->name }}</p>
                                    <p class="mt-1 text-xs text-zinc-500" dir="ltr">{{ $output->code }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :tone="$output->is_active ? 'emerald' : 'zinc'">{{ $output->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="max-w-xs truncate text-xs leading-6 text-zinc-600">{{ $output->prompt }}</p>
                                </td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($output->credit_cost) }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($output->model_output_configs_count) }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($output->generation_requests_count) }}</td>
                                <td class="px-4 py-3" dir="ltr">{{ number_format($output->sort_order) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12">
                                    <x-admin.empty-state icon="gallery">{{ __('ai_models.empty.category_outputs') }}</x-admin.empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @forelse($category->outputs as $output)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div class="min-w-0">
                                <p class="admin-mobile-list-card-title">{{ $output->name }}</p>
                                <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $output->code }}</p>
                            </div>
                            <x-admin.table.action-menu>
                                @if(auth()->user()?->hasPermission('ai_model_category_outputs.manage'))
                                    <label for="show-edit-output-{{ $output->id }}-toggle" class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-right text-xs font-bold text-zinc-700 transition hover:bg-teal-50 hover:text-teal-700" role="button" tabindex="0" data-table-action-item data-admin-modal-trigger="show-edit-output-{{ $output->id }}">
                                        <x-admin.icon name="edit" class="h-4 w-4" />
                                        <span>{{ __('messages.edit') }}</span>
                                    </label>
                                    <x-admin.table.action-item :action="route('admin.system.ai-model-categories.outputs.destroy', [$category, $output])" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                                @endif
                            </x-admin.table.action-menu>
                        </div>
                        <dl class="admin-mobile-list-fields">
                            <x-admin.mobile-list.field :label="__('ai_models.status')">
                                <x-admin.badge :tone="$output->is_active ? 'emerald' : 'zinc'">{{ $output->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge>
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('ai_models.prompt')">
                                <span class="line-clamp-2">{{ $output->prompt }}</span>
                            </x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('ai_models.outputs.credit_cost')">{{ number_format($output->credit_cost) }}</x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('ai_models.categories.models_count')">{{ number_format($output->model_output_configs_count) }}</x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('ai_models.categories.usage_references')">{{ number_format($output->generation_requests_count) }}</x-admin.mobile-list.field>
                        </dl>
                    </x-admin.mobile-list.card>
                @empty
                    <x-admin.mobile-list.card>
                        <x-admin.empty-state icon="gallery">{{ __('ai_models.empty.category_outputs') }}</x-admin.empty-state>
                    </x-admin.mobile-list.card>
                @endforelse
            </x-admin.mobile-list>

            @foreach($category->outputs as $output)
                <x-admin.modal id="show-edit-output-{{ $output->id }}" :label="__('messages.edit')" :title="$output->name" icon="edit" :trigger="false" :open="$errors->any() && old('_form') === 'show_edit_output_'.$output->id">
                    <form method="POST" action="{{ route('admin.system.ai-model-categories.outputs.update', [$category, $output]) }}" class="grid gap-3 md:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="show_edit_output_{{ $output->id }}">
                        <x-admin.validation-summary for="show_edit_output_{{ $output->id }}" class="md:col-span-2" />
                        <input name="name" value="{{ old('name', $output->name) }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('system.name') }}">
                        <input name="credit_cost" value="{{ old('credit_cost', $output->credit_cost) }}" type="text" inputmode="numeric" data-number-input min="0" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.outputs.credit_cost') }}">
                        <input name="sort_order" value="{{ old('sort_order', $output->sort_order) }}" type="text" inputmode="numeric" data-number-input min="0" dir="ltr" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('ai_models.categories.sort_placeholder') }}">
                        <label class="inline-flex h-11 items-center gap-2 rounded-lg border border-zinc-200 px-3 text-sm font-bold">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $output->is_active))>
                            <span>{{ __('ai_models.outputs.active') }}</span>
                        </label>
                        <textarea name="description" rows="3" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('ai_models.categories.description_placeholder') }}">{{ old('description', $output->description) }}</textarea>
                        <textarea name="prompt" rows="6" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none focus:border-teal-500 md:col-span-2" placeholder="{{ __('ai_models.outputs.prompt_placeholder') }}">{{ old('prompt', $output->prompt) }}</textarea>
                        <x-admin.button type="submit" icon="check" class="md:col-span-2">
                            <span>{{ __('messages.save') }}</span>
                        </x-admin.button>
                    </form>
                </x-admin.modal>
            @endforeach
        </x-admin.card>
    </div>
@endsection
