@extends('admin.layout')

@section('title', __('notifications.title'))
@section('page_title', __('notifications.title'))

@php
    $importanceTone = fn (string $importance): string => match ($importance) {
        'urgent' => 'rose',
        'important' => 'amber',
        'info' => 'teal',
        default => 'zinc',
    };
    $oldSelectedTenantIds = collect(old('target_tenant_ids', []))->map(fn ($id) => (string) $id)->all();
    $oldTargetScope = old('target_scope', 'all');
    $oldSendTiming = old('send_timing', 'immediate');
    $oldBodyHtml = app(\App\Support\Html\SimpleRichTextSanitizer::class)->sanitize(old('body_html'));
    $categoryIdsByCode = $categories->pluck('id', 'code');
@endphp

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('notifications.title') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('notifications.subtitle') }}</p>
            </div>

            <x-admin.modal id="create-clinic-notification-modal" :label="__('notifications.create')" :title="__('notifications.create')" icon="bell" :open="$errors->any() && old('_form') === 'create_notification'">
                <form method="POST" action="{{ route('admin.system.notifications.store') }}" class="grid gap-3 md:grid-cols-2" data-notification-form>
                    @csrf
                    <input type="hidden" name="_form" value="create_notification">
                    <x-admin.validation-summary for="create_notification" class="md:col-span-2" />

                    <label class="grid gap-1 text-sm font-bold md:col-span-2">
                        <span>{{ __('notifications.subject') }}</span>
                        <input name="title" value="{{ old('title') }}" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                    </label>

                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('notifications.type') }}</span>
                        <select name="type" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach($types as $type => $label)
                                <option value="{{ $type }}" @selected(old('type', 'general') === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="grid gap-1 text-sm font-bold">
                        <span>{{ __('notifications.importance') }}</span>
                        <select name="importance" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                            @foreach($importances as $importance => $label)
                                <option value="{{ $importance }}" @selected(old('importance', 'normal') === $importance)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="grid gap-3 md:col-span-2" data-notification-schedule>
                        <label class="flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm font-bold">
                            <input type="hidden" name="send_timing" value="scheduled">
                            <input type="checkbox" name="send_timing" value="immediate" @checked($oldSendTiming === 'immediate') class="rounded border-zinc-300 text-teal-600 focus:ring-teal-500" data-notification-immediate>
                            <span>{{ __('notifications.immediate_send') }}</span>
                        </label>

                        <div class="grid gap-3 md:grid-cols-2" data-notification-scheduled-fields>
                            <label class="grid gap-1 text-sm font-bold">
                                <span>{{ __('notifications.visible_from') }}</span>
                                <input name="visible_from" value="{{ old('visible_from', \App\Support\JalaliDate::nowDateTimeString()) }}" dir="ltr" inputmode="numeric" data-jalali-datetime class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('dates.placeholders.datetime') }}">
                            </label>

                            <label class="grid gap-1 text-sm font-bold">
                                <span>{{ __('notifications.visible_until') }}</span>
                                <input name="visible_until" value="{{ old('visible_until') }}" dir="ltr" inputmode="numeric" data-jalali-datetime class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" placeholder="{{ __('dates.placeholders.datetime') }}">
                            </label>
                        </div>
                    </div>

                    <fieldset class="grid gap-3 rounded-lg border border-zinc-200 p-3 md:col-span-2">
                        <legend class="px-2 text-sm font-bold">{{ __('notifications.targeting') }}</legend>
                        <label class="grid gap-1 text-sm font-bold">
                            <span>{{ __('notifications.target_scope') }}</span>
                            <select name="target_scope" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500">
                                @foreach(__('notifications.target_scopes') as $scope => $label)
                                    <option value="{{ $scope }}" @selected(old('target_scope', 'all') === $scope)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="grid gap-3">
                            <div class="grid gap-2" data-notification-tenant-picker>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-bold">{{ __('notifications.target_tenants') }}</span>
                                    <div class="flex items-center gap-2">
                                        <x-admin.button type="button" variant="secondary-muted" size="compact-sm" data-notification-select-all>
                                            {{ __('notifications.select_all_tenants') }}
                                        </x-admin.button>
                                        <x-admin.button type="button" variant="secondary-muted" size="compact-sm" data-notification-clear-tenants>
                                            {{ __('notifications.clear_tenants') }}
                                        </x-admin.button>
                                    </div>
                                </div>
                                <div class="max-h-64 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2" data-notification-tenant-list data-all-count="{{ $tenants->count() }}">
                                    @foreach($tenants as $targetTenant)
                                        @php
                                            $checked = $oldTargetScope === 'all' || in_array((string) $targetTenant->id, $oldSelectedTenantIds, true);
                                            $tenantCategoryIds = $targetTenant->serviceEntitlements
                                                ->map(fn ($entitlement) => $categoryIdsByCode[$entitlement->service_key] ?? null)
                                                ->filter()
                                                ->unique()
                                                ->values()
                                                ->implode(',');
                                            $tenantModelAccessIds = $targetTenant->aiModelAccesses->pluck('id')->unique()->values()->implode(',');
                                        @endphp
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold transition hover:bg-zinc-50">
                                            <input
                                                type="checkbox"
                                                name="target_tenant_ids[]"
                                                value="{{ $targetTenant->id }}"
                                                @checked($checked)
                                                class="rounded border-zinc-300 text-teal-600 focus:ring-teal-500"
                                                data-notification-tenant-checkbox
                                                data-has-active-plan="{{ $targetTenant->subscriptions->isNotEmpty() ? '1' : '0' }}"
                                                data-category-ids="{{ $tenantCategoryIds }}"
                                                data-model-access-ids="{{ $tenantModelAccessIds }}"
                                            >
                                            <span>{{ $targetTenant->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p
                                    class="text-xs leading-6 text-zinc-500"
                                    data-notification-tenant-summary
                                    data-all-label="{{ __('notifications.all_tenants_selected') }}"
                                    data-none-label="{{ __('notifications.no_tenants_selected') }}"
                                    data-selected-label="{{ __('notifications.selected_tenants_count') }}"
                                >{{ __('notifications.all_tenants_selected') }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="grid gap-1 text-sm font-bold">
                                <span>{{ __('notifications.category_filter') }}</span>
                                <select name="filter_ai_model_category_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" data-notification-filter>
                                    <option value="">{{ __('notifications.all_categories') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('filter_ai_model_category_id') === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="grid gap-1 text-sm font-bold">
                                <span>{{ __('notifications.model_filter') }}</span>
                                <select name="filter_ai_model_access_id" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" data-notification-filter>
                                    <option value="">{{ __('notifications.all_models') }}</option>
                                    @foreach($aiModelAccesses as $access)
                                        <option value="{{ $access->id }}" @selected((string) old('filter_ai_model_access_id') === (string) $access->id)>{{ $access->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="grid gap-1 text-sm font-bold">
                                <span>{{ __('notifications.plan_filter') }}</span>
                                <select name="filter_plan_state" class="h-11 rounded-lg border border-zinc-200 px-3 text-sm outline-none focus:border-teal-500" data-notification-filter>
                                    @foreach(__('notifications.plan_states') as $state => $label)
                                        <option value="{{ $state }}" @selected(old('filter_plan_state', 'any') === $state)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <p class="text-xs leading-6 text-zinc-500">{{ __('notifications.filters_hint') }}</p>
                        <p
                            class="rounded-lg bg-teal-50 px-3 py-2 text-xs font-bold leading-6 text-teal-800"
                            data-notification-match-count
                            data-count-label="{{ __('notifications.matching_tenants_count') }}"
                            data-zero-label="{{ __('notifications.matching_tenants_zero') }}"
                        ></p>
                    </fieldset>

                    <label class="grid gap-1 text-sm font-bold md:col-span-2" data-notification-composer>
                        <span>{{ __('notifications.message') }}</span>
                        <div class="admin-rich-text-toolbar" role="toolbar" aria-label="{{ __('notifications.editor_toolbar') }}">
                            <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="bold" title="{{ __('notifications.editor.bold') }}" aria-label="{{ __('notifications.editor.bold') }}">
                                <strong>B</strong>
                            </x-admin.button>
                            <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="italic" title="{{ __('notifications.editor.italic') }}" aria-label="{{ __('notifications.editor.italic') }}">
                                <em>I</em>
                            </x-admin.button>
                            <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="underline" title="{{ __('notifications.editor.underline') }}" aria-label="{{ __('notifications.editor.underline') }}">
                                <u>U</u>
                            </x-admin.button>
                            <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="insertUnorderedList" title="{{ __('notifications.editor.bullets') }}" aria-label="{{ __('notifications.editor.bullets') }}">
                                <span>•</span>
                            </x-admin.button>
                            <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-command="insertOrderedList" title="{{ __('notifications.editor.numbers') }}" aria-label="{{ __('notifications.editor.numbers') }}">
                                <span>1</span>
                            </x-admin.button>
                            <x-admin.button type="button" variant="secondary-muted" size="icon-sm" data-rich-text-link data-link-prompt="{{ __('notifications.editor.link_prompt') }}" title="{{ __('notifications.editor.link') }}" aria-label="{{ __('notifications.editor.link') }}">
                                <x-admin.icon name="link" class="h-4 w-4" />
                            </x-admin.button>
                        </div>
                        <div class="admin-rich-text-editor" contenteditable="true" dir="rtl" data-rich-text-editor>{!! $oldBodyHtml !!}</div>
                        <textarea name="body_html" class="sr-only" data-rich-text-input>{{ $oldBodyHtml }}</textarea>
                    </label>

                    <x-admin.button type="submit" icon="check" class="md:col-span-2">
                        <span>{{ __('messages.save') }}</span>
                    </x-admin.button>
                </form>
            </x-admin.modal>
        </div>

        <x-admin.list-filters
            class="mb-5 mt-5"
            preset-key="admin.system.notifications.index"
            :search-placeholder="__('messages.search_placeholder')"
            :filters="[
                ['name' => 'type', 'label' => __('notifications.type'), 'type' => 'select', 'placeholder' => __('messages.select'), 'options' => $types],
                ['name' => 'importance', 'label' => __('notifications.importance'), 'type' => 'select', 'placeholder' => __('messages.select'), 'options' => $importances],
            ]"
            :sorts="[
                'created_at' => __('messages.created_at'),
                'visible_from' => __('notifications.visible_from'),
                'title' => __('notifications.subject'),
                'type' => __('notifications.type'),
                'importance' => __('notifications.importance'),
            ]"
        />

        <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-5xl text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.subject') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.type') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.importance') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.delivery_time') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.delivered_count') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.unread_count') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($notifications as $notification)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    <x-admin.table.action-item :href="route('admin.system.notifications.show', $notification)" icon="eye">{{ __('notifications.delivery_status') }}</x-admin.table.action-item>
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold text-zinc-950">{{ $notification->title }}</p>
                                <p class="mt-1 line-clamp-2 text-xs leading-6 text-zinc-500">{{ $notification->body }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $types[$notification->type] ?? $notification->type }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$importanceTone($notification->importance)">{{ $importances[$notification->importance] ?? $notification->importance }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3"><x-jalali-date-time :value="$notification->visible_from" /></td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($notification->recipients_count) }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ number_format($notification->unread_recipients_count) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12">
                                <x-admin.empty-state icon="bell">{{ __('notifications.empty_management') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($notifications as $notification)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div>
                            <p class="admin-mobile-list-card-title">{{ $notification->title }}</p>
                            <p class="admin-mobile-list-card-subtitle">{{ $types[$notification->type] ?? $notification->type }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-admin.badge :tone="$importanceTone($notification->importance)">{{ $importances[$notification->importance] ?? $notification->importance }}</x-admin.badge>
                            <x-admin.table.action-menu>
                                <x-admin.table.action-item :href="route('admin.system.notifications.show', $notification)" icon="eye">{{ __('notifications.delivery_status') }}</x-admin.table.action-item>
                            </x-admin.table.action-menu>
                        </div>
                    </div>
                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('notifications.delivery_time')"><x-jalali-date-time :value="$notification->visible_from" /></x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('notifications.delivered_count')">{{ number_format($notification->recipients_count) }}</x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('notifications.unread_count')">{{ number_format($notification->unread_recipients_count) }}</x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="bell">{{ __('notifications.empty_management') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$notifications" class="mt-5" />
    </x-admin.card>
@endsection
