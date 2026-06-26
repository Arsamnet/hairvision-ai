@extends('admin.layout')

@section('title', __('system.sms_gateways_title'))
@section('page_title', __('system.sms_gateways_title'))

@section('content')
    @can('permission', 'sms_gateways.create')
        <section class="mb-5 rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 class="text-lg font-bold">{{ __('system.new_gateway') }}</h2>
            <form method="POST" action="{{ route('admin.system.sms-gateways.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <input name="name" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="{{ __('system.name') }}">
                <input name="provider_code" value="local" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" dir="ltr">
                <select name="owner_scope" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    @foreach(['system', 'user'] as $owner)
                        <option value="{{ $owner }}">{{ __('system.owners.'.$owner) }}</option>
                    @endforeach
                </select>
                <select name="user_id" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    <option value="">{{ __('system.no_private_user') }}</option>
                    @foreach($users as $userOption)
                        <option value="{{ $userOption->id }}">{{ $userOption->name }}</option>
                    @endforeach
                </select>
                <input name="api_key" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="API key" dir="ltr">
                <input name="sender" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm" placeholder="sender" dir="ltr">
                <select name="status" class="rounded-lg border border-zinc-200 px-3 py-2 text-sm">
                    @foreach(['enabled', 'disabled'] as $status)
                        <option value="{{ $status }}">{{ __('system.statuses.'.$status) }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="is_default" value="1"> {{ __('system.default') }}</label>
                <button class="rounded-lg bg-zinc-950 px-4 py-2 text-sm font-bold text-white">{{ __('system.submit') }}</button>
            </form>
        </section>
    @endcan

    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[54rem] text-right text-sm">
                <thead class="border-b border-zinc-200 text-xs text-zinc-500">
                    <tr>
                        <th class="py-3">{{ __('system.name') }}</th>
                        <th class="py-3">{{ __('system.provider') }}</th>
                        <th class="py-3">{{ __('system.owner') }}</th>
                        <th class="py-3">{{ __('system.status') }}</th>
                        <th class="py-3">{{ __('system.default') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach($gateways as $gateway)
                        <tr>
                            <td class="py-3 font-bold">{{ $gateway->name }}</td>
                            <td class="py-3" dir="ltr">{{ $gateway->provider_code }}</td>
                            <td class="py-3">{{ $gateway->owner_scope === 'user' ? $gateway->user?->name : __('system.owners.system') }}</td>
                            <td class="py-3">{{ __('system.statuses.'.$gateway->status) }}</td>
                            <td class="py-3">{{ $gateway->is_default ? __('messages.yes') : __('messages.no') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $gateways->links() }}</div>
    </section>
@endsection
