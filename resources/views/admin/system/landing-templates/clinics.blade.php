@extends('admin.layout')

@section('title', __('landing_templates.clinics_tab'))
@section('page_title', __('landing_templates.title').' - '.__('landing_templates.clinics_tab'))

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('landing_templates.clinics_tab') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('landing_templates.default_new_clinics_hint') }}</p>
            </div>
            <x-admin.button :href="route('admin.system.landing-templates.index')" variant="secondary" icon="gallery">
                {{ __('landing_templates.templates_tab') }}
            </x-admin.button>
        </div>

        <x-admin.list-filters
            class="mb-5"
            preset-key="admin.system.landing-templates.clinics"
            :search-placeholder="__('messages.search_placeholder')"
            :filters="[
                ['name' => 'status', 'label' => __('clinics.fields.status'), 'type' => 'select', 'placeholder' => __('clinics.all_statuses'), 'options' => \App\Support\StateCatalog::options('tenants.statuses')],
            ]"
            :sorts="[
                'name' => __('clinics.fields.name'),
                'created_at' => __('messages.created_at'),
                'slug' => __('clinics.fields.slug'),
                'status' => __('clinics.fields.status'),
            ]"
        />

        <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-232 text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('clinics.fields.name') }}</th>
                        <th class="px-4 py-3">{{ __('clinics.fields.status') }}</th>
                        <th class="px-4 py-3">{{ __('landing_templates.fields.template') }}</th>
                        <th class="px-4 py-3">{{ __('landing_templates.fields.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($clinics as $clinic)
                        @php
                            $assignedTemplate = $clinic->theme?->template;
                            $usesCentralTemplate = (bool) $clinic->theme?->use_template_content;
                            $effectiveTemplate = $usesCentralTemplate && $assignedTemplate?->is_active
                                ? $assignedTemplate
                                : $defaultTemplate;
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-bold text-zinc-950">{{ $clinic->name }}</p>
                                <p class="mt-1 text-xs text-zinc-500" dir="ltr">{{ $clinic->slug }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$clinic->status === 'active' ? 'emerald' : 'zinc'">{{ \App\Support\StateCatalog::label('tenants.statuses', $clinic->status) }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.system.landing-templates.clinics.assign', $clinic) }}" class="flex min-w-[20rem] items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="template_id" class="h-10 min-w-0 flex-1 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                                        @foreach($landingTemplates as $landingTemplate)
                                            <option value="{{ $landingTemplate->id }}" @selected((int) old('template_id', $effectiveTemplate?->id) === $landingTemplate->id)>
                                                {{ $landingTemplate->name }}{{ $landingTemplate->is_default ? ' — '.__('landing_templates.fields.default') : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-admin.button type="submit" size="sm" icon="check">{{ __('landing_templates.actions.assign') }}</x-admin.button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                @if($clinic->theme && ! $usesCentralTemplate)
                                    <x-admin.badge tone="amber">{{ __('landing_templates.statuses.legacy') }}</x-admin.badge>
                                    <p class="mt-2 max-w-xs text-xs leading-6 text-zinc-500">{{ __('landing_templates.legacy_hint') }}</p>
                                @elseif($clinic->theme)
                                    <x-admin.badge tone="teal">{{ __('landing_templates.statuses.assigned') }}</x-admin.badge>
                                @else
                                    <x-admin.badge tone="zinc">{{ __('landing_templates.statuses.inherited') }}</x-admin.badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12">
                                <x-admin.empty-state icon="building">{{ __('landing_templates.empty.clinics') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($clinics as $clinic)
                @php
                    $assignedTemplate = $clinic->theme?->template;
                    $usesCentralTemplate = (bool) $clinic->theme?->use_template_content;
                    $effectiveTemplate = $usesCentralTemplate && $assignedTemplate?->is_active
                        ? $assignedTemplate
                        : $defaultTemplate;
                @endphp
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div>
                            <p class="admin-mobile-list-card-title">{{ $clinic->name }}</p>
                            <p class="admin-mobile-list-card-subtitle" dir="ltr">{{ $clinic->slug }}</p>
                        </div>
                        <x-admin.badge :tone="$clinic->status === 'active' ? 'emerald' : 'zinc'">{{ \App\Support\StateCatalog::label('tenants.statuses', $clinic->status) }}</x-admin.badge>
                    </div>
                    <form method="POST" action="{{ route('admin.system.landing-templates.clinics.assign', $clinic) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PUT')
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('landing_templates.fields.template') }}</span>
                            <select name="template_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                                @foreach($landingTemplates as $landingTemplate)
                                    <option value="{{ $landingTemplate->id }}" @selected((int) old('template_id', $effectiveTemplate?->id) === $landingTemplate->id)>
                                        {{ $landingTemplate->name }}{{ $landingTemplate->is_default ? ' — '.__('landing_templates.fields.default') : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        @if($clinic->theme && ! $usesCentralTemplate)
                            <p class="text-xs leading-6 text-amber-800">{{ __('landing_templates.legacy_hint') }}</p>
                        @endif
                        <x-admin.button type="submit" size="sm" icon="check" block>{{ __('landing_templates.actions.assign') }}</x-admin.button>
                    </form>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="building">{{ __('landing_templates.empty.clinics') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$clinics" class="mt-5" />
    </x-admin.card>
@endsection
