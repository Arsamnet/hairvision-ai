@extends('admin.layout')

@section('title', __('notifications.inbox'))
@section('page_title', __('notifications.inbox'))

@php
    $importanceTone = fn (string $importance): string => match ($importance) {
        'urgent' => 'rose',
        'important' => 'amber',
        'info' => 'teal',
        default => 'zinc',
    };
@endphp

@section('content')
    <x-admin.card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('notifications.inbox') }}</h2>
                <p class="mt-1 text-sm leading-6 text-zinc-500">{{ __('notifications.inbox_subtitle') }}</p>
            </div>

            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <x-admin.button type="submit" variant="secondary" icon="check">
                    <span>{{ __('notifications.mark_all_read') }}</span>
                </x-admin.button>
            </form>
        </div>

        <x-admin.list-filters
            class="mb-5 mt-5"
            preset-key="admin.notifications.index"
            :search-placeholder="__('messages.search_placeholder')"
            :filters="[
                ['name' => 'read_state', 'label' => __('system.status'), 'type' => 'select', 'placeholder' => __('notifications.all_read_states'), 'options' => ['unread' => __('notifications.unread'), 'read' => __('notifications.read')]],
                ['name' => 'type', 'label' => __('notifications.type'), 'type' => 'select', 'placeholder' => __('messages.select'), 'options' => $types],
                ['name' => 'importance', 'label' => __('notifications.importance'), 'type' => 'select', 'placeholder' => __('messages.select'), 'options' => $importances],
            ]"
            :sorts="[
                'delivered_at' => __('notifications.delivery_time'),
            ]"
        />

        <div data-admin-table-container class="hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
            <table class="w-full min-w-[68rem] text-right text-sm">
                <thead class="bg-zinc-50 text-xs text-zinc-500">
                    <tr>
                        <th class="w-14 px-4 py-3">{{ __('messages.actions') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.subject') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.type') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.importance') }}</th>
                        <th class="px-4 py-3">{{ __('notifications.delivery_time') }}</th>
                        <th class="px-4 py-3">{{ __('system.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($recipients as $recipient)
                        @php($notification = $recipient->notification)
                        <tr class="transition hover:bg-zinc-50/80">
                            <td class="px-4 py-3 align-top">
                                <x-admin.table.action-menu>
                                    <x-admin.table.action-item :href="route('admin.notifications.show', $recipient)" icon="eye">{{ __('messages.view') }}</x-admin.table.action-item>
                                </x-admin.table.action-menu>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-bold {{ $recipient->read_at ? 'text-zinc-950' : 'text-teal-700' }}">{{ $notification->title }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $types[$notification->type] ?? $notification->type }}</td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$importanceTone($notification->importance)">{{ $importances[$notification->importance] ?? $notification->importance }}</x-admin.badge>
                            </td>
                            <td class="px-4 py-3"><x-jalali-date-time :value="$notification->visible_from" /></td>
                            <td class="px-4 py-3">
                                <x-admin.badge :tone="$recipient->read_at ? 'emerald' : 'amber'">{{ $recipient->read_at ? __('notifications.read') : __('notifications.unread') }}</x-admin.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <x-admin.empty-state icon="bell">{{ __('notifications.empty_inbox') }}</x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.mobile-list>
            @forelse($recipients as $recipient)
                @php($notification = $recipient->notification)
                <x-admin.mobile-list.card>
                    <div class="admin-mobile-list-card-header">
                        <div>
                            <p class="admin-mobile-list-card-title {{ $recipient->read_at ? '' : 'text-teal-700' }}">{{ $notification->title }}</p>
                            <p class="admin-mobile-list-card-subtitle">{{ $types[$notification->type] ?? $notification->type }}</p>
                        </div>
                        <x-admin.table.action-menu>
                            <x-admin.table.action-item :href="route('admin.notifications.show', $recipient)" icon="eye">{{ __('messages.view') }}</x-admin.table.action-item>
                        </x-admin.table.action-menu>
                    </div>
                    <dl class="admin-mobile-list-fields">
                        <x-admin.mobile-list.field :label="__('notifications.importance')">
                            <x-admin.badge :tone="$importanceTone($notification->importance)">{{ $importances[$notification->importance] ?? $notification->importance }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('notifications.delivery_time')"><x-jalali-date-time :value="$notification->visible_from" /></x-admin.mobile-list.field>
                        <x-admin.mobile-list.field :label="__('system.status')">
                            <x-admin.badge :tone="$recipient->read_at ? 'emerald' : 'amber'">{{ $recipient->read_at ? __('notifications.read') : __('notifications.unread') }}</x-admin.badge>
                        </x-admin.mobile-list.field>
                    </dl>
                </x-admin.mobile-list.card>
            @empty
                <x-admin.mobile-list.card>
                    <x-admin.empty-state icon="bell">{{ __('notifications.empty_inbox') }}</x-admin.empty-state>
                </x-admin.mobile-list.card>
            @endforelse
        </x-admin.mobile-list>

        <x-admin.table.pagination-footer :paginator="$recipients" class="mt-5" />
    </x-admin.card>
@endsection
