@extends('admin.layout')

@section('title', __('ai_models.assignment_title'))
@section('page_title', __('ai_models.assignment_title'))

@section('content')
    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $clinic->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ai_models.assignment_title') }}</p>
                </div>
                <a href="{{ route('admin.ai-models.index') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-zinc-200 px-4 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700">
                    <x-admin.icon name="sparkles" class="h-4 w-4" />
                    <span>{{ __('ai_models.title') }}</span>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.clinics.ai-models.store', $clinic) }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_10rem_8rem]">
                @csrf
                <select name="ai_model_access_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                    @foreach($availableAccesses as $access)
                        <option value="{{ $access->id }}">{{ $access->name }} - {{ $resolver->usageCategoryLabel($access->usage_category_key) }}</option>
                    @endforeach
                </select>
                <label class="flex h-11 items-center gap-2 rounded-lg bg-zinc-50 px-3 text-sm font-bold">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>{{ __('ai_models.statuses.active') }}</span>
                </label>
                <button class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700">
                    <x-admin.icon name="plus" class="h-4 w-4" />
                    <span>{{ __('ai_models.assign') }}</span>
                </button>
            </form>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.assigned_clinics') }}</h3>
            <div class="mt-4 overflow-visible rounded-lg border border-zinc-100">
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
                                        <x-admin.table.action-item :href="route('admin.ai-models.show', $access)" icon="eye">{{ __('ai_models.details') }}</x-admin.table.action-item>
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
        </x-admin.card>
    </div>
@endsection
