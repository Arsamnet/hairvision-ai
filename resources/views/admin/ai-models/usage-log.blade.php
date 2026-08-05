{{-- @since 2.1.2 Renders safe system-only AI model usage diagnostics. --}}
@extends('admin.layout')

@section('title', __('ai_models.usage_diagnostics_title'))
@section('page_title', __('ai_models.usage_diagnostics_title'))

@section('content')
    @php
        $metadata = $usageLog->metadata_json ?? [];
        $requestMetadata = data_get($metadata, 'request', []);
        $timing = data_get($metadata, 'timing', []);
        $providerResponse = data_get($metadata, 'provider_response', []);
        $providerMetadata = data_get($providerResponse, 'metadata', []);
        $providerError = data_get($providerMetadata, 'provider_error', []);
        $usageLead = $usageLog->lead && (int) $usageLog->lead->tenant_id === (int) $usageLog->clinic_id
            ? $usageLog->lead
            : null;
        $canViewLead = $usageLead && auth()->user()?->can('view', $usageLead);
    @endphp

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-admin.button :href="route('admin.ai-models.show', $aiModelAccess)" variant="secondary" size="sm" icon="arrow-right">
                {{ __('ai_models.back_to_model') }}
            </x-admin.button>
        </div>

        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('ai_models.usage_diagnostics_summary') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ai_models.usage_diagnostics_hint') }}</p>
                </div>
                <x-admin.badge :tone="$usageLog->status === 'completed' ? 'emerald' : ($usageLog->status === 'failed' ? 'rose' : 'zinc')">
                    {{ __('ai_models.statuses.'.$usageLog->status) }}
                </x-admin.badge>
            </div>

            <dl class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('system.clinic') }}</dt>
                    <dd class="mt-1 font-bold">{{ $usageLog->clinic?->name ?? __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.model') }}</dt>
                    <dd class="mt-1 font-bold">{{ $usageLog->aiModelAccess?->name ?? __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.usage_type') }}</dt>
                    <dd class="mt-1 font-bold">{{ $resolver->usageCategoryLabel($usageLog->usage_category_key) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('leads.fields.phone') }}</dt>
                    <dd class="mt-1 font-bold">
                        @if($canViewLead)
                            <a class="text-teal-700 hover:text-teal-900" dir="ltr" href="{{ route('admin.leads.show', $usageLead) }}">{{ $usageLead->phone_normalized }}</a>
                        @else
                            {{ __('messages.none') }}
                        @endif
                    </dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.credit_used') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ number_format($usageLog->credit_consumed) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.request_reference') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ $usageLog->request_reference ?? __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.response_reference') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ $usageLog->response_reference ?? __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.error_code') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ $usageLog->error_code ?? data_get($providerResponse, 'error_code', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.error_message') }}</dt>
                    <dd class="mt-1 font-bold">{{ $usageLog->error_message ?? data_get($providerResponse, 'safe_error_message', __('messages.none')) }}</dd>
                </div>
            </dl>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.usage_timing') }}</h3>
            <dl class="mt-4 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.started_at') }}</dt>
                    <dd class="mt-1 font-bold"><x-jalali-date-time :value="$usageLog->started_at" /></dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider_call_started_at') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($timing, 'provider_call_started_at', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider_call_finished_at') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($timing, 'provider_call_finished_at', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider_elapsed_ms') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ is_numeric(data_get($timing, 'provider_elapsed_ms')) ? number_format((int) data_get($timing, 'provider_elapsed_ms')).' ms' : __('messages.none') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.completed_at') }}</dt>
                    <dd class="mt-1 font-bold"><x-jalali-date-time :value="$usageLog->completed_at" /></dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.failed_at') }}</dt>
                    <dd class="mt-1 font-bold"><x-jalali-date-time :value="$usageLog->failed_at" /></dd>
                </div>
            </dl>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.provider_response_summary') }}</h3>
            <dl class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ data_get($providerMetadata, 'provider', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.http_status') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ data_get($providerMetadata, 'http_status', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.retryable') }}</dt>
                    <dd class="mt-1 font-bold">{{ data_get($providerResponse, 'retryable') ? __('messages.yes') : __('messages.no') }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider_error_type') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($providerError, 'type', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider_error_code') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($providerError, 'code', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.provider_message_hash') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($providerError, 'message_sha256', __('messages.none')) }}</dd>
                </div>
            </dl>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.request_context') }}</h3>
            <dl class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.generation_request_uuid') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($requestMetadata, 'generation_request_uuid', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.generation_attempt_uuid') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($requestMetadata, 'generation_attempt_uuid', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.output_key') }}</dt>
                    <dd class="mt-1 break-all font-bold" dir="ltr">{{ data_get($requestMetadata, 'output_key', __('messages.none')) }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <dt class="text-xs font-bold text-zinc-500">{{ __('ai_models.prompt_length') }}</dt>
                    <dd class="mt-1 font-bold" dir="ltr">{{ data_get($requestMetadata, 'prompt_length', __('messages.none')) }}</dd>
                </div>
            </dl>
        </x-admin.card>

        <x-admin.card>
            <h3 class="text-base font-bold">{{ __('ai_models.safe_metadata_json') }}</h3>
            <p class="mt-1 text-xs leading-6 text-zinc-500">{{ __('ai_models.safe_metadata_hint') }}</p>
            <pre class="mt-4 max-h-140 overflow-auto rounded-lg bg-zinc-950 p-4 text-left text-xs leading-6 text-zinc-100" dir="ltr"><code>{{ $metadataJson ?: '{}' }}</code></pre>
        </x-admin.card>
    </div>
@endsection
