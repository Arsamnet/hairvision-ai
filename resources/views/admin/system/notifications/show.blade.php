@extends('admin.layout')

@section('title', $notification->title)
@section('page_title', __('notifications.delivery_status'))

@php
    $readRecipients = $notification->recipients->filter(fn ($recipient) => $recipient->read_at !== null);
    $unreadRecipients = $notification->recipients->filter(fn ($recipient) => $recipient->read_at === null);
    $importanceTone = $notification->importanceTone();
@endphp

@section('content')
    <div class="grid gap-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-zinc-950">{{ $notification->title }}</h2>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-admin.badge tone="teal">{{ $types[$notification->type] ?? $notification->type }}</x-admin.badge>
                        <x-admin.badge :tone="$importanceTone">{{ $importances[$notification->importance] ?? $notification->importance }}</x-admin.badge>
                    </div>
                </div>
                <x-admin.button :href="route('admin.system.notifications.index')" variant="secondary" icon="chevron-right">
                    <span>{{ __('notifications.back_to_management') }}</span>
                </x-admin.button>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-zinc-50 p-4">
                    <p class="text-xs font-bold text-zinc-500">{{ __('notifications.delivered_count') }}</p>
                    <p class="mt-2 text-2xl font-bold text-zinc-950" dir="ltr">{{ number_format($notification->recipients->count()) }}</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-4">
                    <p class="text-xs font-bold text-emerald-700">{{ __('notifications.read') }}</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-800" dir="ltr">{{ number_format($readRecipients->count()) }}</p>
                </div>
                <div class="rounded-lg bg-amber-50 p-4">
                    <p class="text-xs font-bold text-amber-700">{{ __('notifications.unread') }}</p>
                    <p class="mt-2 text-2xl font-bold text-amber-800" dir="ltr">{{ number_format($unreadRecipients->count()) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('notifications.delivery_recipients') }}</h3>
            <div data-admin-table-container class="mt-5 hidden overflow-visible rounded-lg border border-zinc-100 lg:block">
                <table class="w-full min-w-232 text-right text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('system.clinic') }}</th>
                            <th class="px-4 py-3">{{ __('system.status') }}</th>
                            <th class="px-4 py-3">{{ __('notifications.read_by') }}</th>
                            <th class="px-4 py-3">{{ __('notifications.read_at') }}</th>
                            <th class="px-4 py-3">{{ __('notifications.delivery_time') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($notification->recipients as $recipient)
                            <tr>
                                <td class="px-4 py-3 font-bold text-zinc-950">{{ $recipient->tenant?->name ?? __('messages.none') }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :tone="$recipient->read_at ? 'emerald' : 'amber'">
                                        {{ $recipient->read_at ? __('notifications.read') : __('notifications.unread') }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3">{{ $recipient->read_by_user_name ?? __('messages.none') }}</td>
                                <td class="px-4 py-3"><x-jalali-date-time :value="$recipient->read_at" /></td>
                                <td class="px-4 py-3"><x-jalali-date-time :value="$recipient->delivered_at" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-admin.mobile-list>
                @foreach($notification->recipients as $recipient)
                    <x-admin.mobile-list.card>
                        <div class="admin-mobile-list-card-header">
                            <div>
                                <p class="admin-mobile-list-card-title">{{ $recipient->tenant?->name ?? __('messages.none') }}</p>
                                <p class="admin-mobile-list-card-subtitle">{{ $recipient->read_by_user_name ?? __('messages.none') }}</p>
                            </div>
                            <x-admin.badge :tone="$recipient->read_at ? 'emerald' : 'amber'">
                                {{ $recipient->read_at ? __('notifications.read') : __('notifications.unread') }}
                            </x-admin.badge>
                        </div>
                        <dl class="admin-mobile-list-fields">
                            <x-admin.mobile-list.field :label="__('notifications.read_at')"><x-jalali-date-time :value="$recipient->read_at" /></x-admin.mobile-list.field>
                            <x-admin.mobile-list.field :label="__('notifications.delivery_time')"><x-jalali-date-time :value="$recipient->delivered_at" /></x-admin.mobile-list.field>
                        </dl>
                    </x-admin.mobile-list.card>
                @endforeach
            </x-admin.mobile-list>
        </x-admin.card>
    </div>
@endsection
