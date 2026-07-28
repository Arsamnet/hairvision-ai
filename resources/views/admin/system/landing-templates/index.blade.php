@extends('admin.layout')

@section('title', __('landing_templates.title'))
@section('page_title', __('landing_templates.title'))

@php
    $statusOptions = [
        'active' => __('landing_templates.statuses.active'),
        'inactive' => __('landing_templates.statuses.inactive'),
    ];
@endphp

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('landing_templates.title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('landing_templates.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(auth()->user()?->hasPermission('landing_templates.assign'))
                    <x-admin.button :href="route('admin.system.landing-templates.clinics')" variant="secondary" icon="building">
                        {{ __('landing_templates.clinics_tab') }}
                    </x-admin.button>
                @endif
                @if(auth()->user()?->hasPermission('landing_templates.create'))
                    <x-admin.button :href="route('admin.system.landing-templates.create')" icon="plus">
                        {{ __('landing_templates.create') }}
                    </x-admin.button>
                @endif
            </div>
        </div>

        <p class="mt-4 rounded-lg border border-teal-100 bg-teal-50 px-4 py-3 text-sm leading-7 text-teal-900">
            {{ __('landing_templates.default_new_clinics_hint') }}
        </p>

        <x-admin.list-filters
            class="mb-5"
            preset-key="admin.system.landing-templates.index"
            :search-placeholder="__('messages.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('landing_templates.fields.status'), 'type' => 'select', 'placeholder' => __('clinics.all_statuses'), 'options' => $statusOptions],
            ]"
            :sorts="[
                'updated_at' => __('messages.updated_at'),
                'name' => __('landing_templates.fields.name'),
                'code' => __('landing_templates.fields.code'),
                'is_active' => __('landing_templates.fields.status'),
            ]"
        />

        <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-208 text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3"><x-admin.table.sortable-heading sort="name" :label="__('landing_templates.fields.name')" /></th>
                        <th class="px-4 py-3">{{ __('landing_templates.fields.status') }}</th>
                        <th class="px-4 py-3">{{ __('landing_templates.fields.default') }}</th>
                        <th class="px-4 py-3">{{ __('landing_templates.fields.revision') }}</th>
                        <th class="px-4 py-3">{{ __('landing_templates.fields.assignments') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($templates as $landingTemplate)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    @if(auth()->user()?->hasPermission('landing_templates.update'))
                                        <x-admin.table.action-item :href="route('admin.system.landing-templates.edit', $landingTemplate)" icon="edit">{{ __('messages.edit') }}</x-admin.table.action-item>
                                        @if(! $landingTemplate->is_default && $landingTemplate->is_active)
                                            <x-admin.table.action-item :action="route('admin.system.landing-templates.default', $landingTemplate)" method="PATCH" icon="check">
                                                {{ __('landing_templates.actions.make_default') }}
                                            </x-admin.table.action-item>
                                        @endif
                                    @endif
                                    @if(auth()->user()?->hasPermission('landing_templates.delete') && ! $landingTemplate->is_default && $landingTemplate->theme_settings_count === 0)
                                        <x-admin.table.action-item :action="route('admin.system.landing-templates.destroy', $landingTemplate)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                                    @endif
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold text-zinc-950">{{ $landingTemplate->name }}</p>
                                <p class="mt-1 text-xs text-zinc-500" dir="ltr">{{ $landingTemplate->code }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$landingTemplate->is_active ? 'emerald' : 'zinc'">
                                    {{ $landingTemplate->is_active ? __('landing_templates.statuses.active') : __('landing_templates.statuses.inactive') }}
                                </x-admin.badge>
                            </td>
                            <td class="px-4 py-3">
                                @if($landingTemplate->is_default)
                                    <x-admin.badge tone="teal">{{ __('landing_templates.fields.default') }}</x-admin.badge>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($landingTemplate->revision) }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($landingTemplate->theme_settings_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="gallery">{{ __('landing_templates.empty.templates') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($templates as $landingTemplate)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div>
                            <p class="admin-mobile-list-card-title">{{ $landingTemplate->name }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $landingTemplate->code }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            @if(auth()->user()?->hasPermission('landing_templates.update'))
                                <x-admin.table.action-item :href="route('admin.system.landing-templates.edit', $landingTemplate)" icon="edit">{{ __('messages.edit') }}</x-admin.table.action-item>
                                @if(! $landingTemplate->is_default && $landingTemplate->is_active)
                                    <x-admin.table.action-item :action="route('admin.system.landing-templates.default', $landingTemplate)" method="PATCH" icon="check">{{ __('landing_templates.actions.make_default') }}</x-admin.table.action-item>
                                @endif
                            @endif
                            @if(auth()->user()?->hasPermission('landing_templates.delete') && ! $landingTemplate->is_default && $landingTemplate->theme_settings_count === 0)
                                <x-admin.table.action-item :action="route('admin.system.landing-templates.destroy', $landingTemplate)" method="DELETE" icon="trash" danger :confirm="__('messages.confirm_delete')">{{ __('messages.delete') }}</x-admin.table.action-item>
                            @endif
                        </x-admin.table.action-menu>
                    </div>
                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('landing_templates.fields.status')">
                            {{ $landingTemplate->is_active ? __('landing_templates.statuses.active') : __('landing_templates.statuses.inactive') }}
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('landing_templates.fields.default')">
                            {{ $landingTemplate->is_default ? __('messages.yes') : __('messages.no') }}
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('landing_templates.fields.assignments')">{{ number_format($landingTemplate->theme_settings_count) }}</x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="gallery">{{ __('landing_templates.empty.templates') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$templates" class="mt-5" />
    </x-admin.card>
@endsection
