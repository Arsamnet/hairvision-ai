@extends('admin.layout')

@section('title', $recipient->notification->title)
@section('page_title', __('notifications.inbox'))

@php
    $notification = $recipient->notification;
    $importanceTone = match ($notification->importance) {
        'urgent' => 'rose',
        'important' => 'amber',
        'info' => 'teal',
        default => 'zinc',
    };
@endphp

@section('content')
    <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-zinc-950">{{ $notification->title }}</h2>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-admin.badge tone="teal">{{ __('notifications.types.'.$notification->type) }}</x-admin.badge>
                        <x-admin.badge :tone="$importanceTone">{{ __('notifications.importances.'.$notification->importance) }}</x-admin.badge>
                    </div>
                </div>

                <x-admin.button :href="route('admin.notifications.index')" variant="secondary" icon="chevron-right">
                    <span>{{ __('notifications.back_to_inbox') }}</span>
                </x-admin.button>
            </div>

            <div class="admin-rich-text-content mt-6 rounded-lg border border-zinc-100 bg-zinc-50 p-4 text-sm leading-8 text-zinc-700">
                {!! $notification->body_html ?: e($notification->body) !!}
            </div>
        </x-admin.card>

        <x-admin.card>
            <dl class="grid gap-4 text-sm">
                <div>
                    <dt class="text-xs font-bold text-zinc-500">{{ __('notifications.delivery_time') }}</dt>
                    <dd class="mt-1 font-bold text-zinc-950"><x-jalali-date-time :value="$notification->visible_from" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-zinc-500">{{ __('notifications.visible_until') }}</dt>
                    <dd class="mt-1 font-bold text-zinc-950"><x-jalali-date-time :value="$notification->visible_until" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-bold text-zinc-500">{{ __('notifications.read_at') }}</dt>
                    <dd class="mt-1 font-bold text-zinc-950"><x-jalali-date-time :value="$recipient->read_at" /></dd>
                </div>
            </dl>
        </x-admin.card>
    </div>
@endsection
