@extends('admin.layout')

@section('title', __('ai_models.edit'))
@section('page_title', __('ai_models.edit'))

@section('content')
    @php
        $modelConfig = $resolver->model($aiModelAccess->model_key);
        $requiredCredentials = collect($modelConfig['required_credentials'] ?? []);
        $credentialsConfigured = $requiredCredentials->isEmpty()
            || $requiredCredentials->every(fn (string $field): bool => filled(data_get($aiModelAccess->credentials ?? [], $field)));
    @endphp

    <div class="space-y-5">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold text-teal-600">{{ $resolver->modelLabel($aiModelAccess->model_key) }}</p>
                    <h2 class="mt-1 text-lg font-bold">{{ $aiModelAccess->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ $resolver->usageCategoryLabel($aiModelAccess->usage_category_key) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-admin.button :href="route('admin.ai-models.show', $aiModelAccess)" variant="secondary" size="sm" icon="eye">
                        <span>{{ __('ai_models.details') }}</span>
                    </x-admin.button>
                    <x-admin.button :href="route('admin.ai-models.index')" variant="secondary" size="sm" icon="sparkles">
                        <span>{{ __('ai_models.back_to_list') }}</span>
                    </x-admin.button>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.status') }}</p>
                    <p class="mt-1"><x-admin.badge :tone="$aiModelAccess->is_active ? 'emerald' : 'zinc'">{{ $aiModelAccess->is_active ? __('ai_models.statuses.active') : __('ai_models.statuses.inactive') }}</x-admin.badge></p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.credential_status') }}</p>
                    <p class="mt-1 font-bold">{{ $credentialsConfigured ? __('ai_models.credentials_configured') : __('ai_models.credentials_missing') }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.usage_count') }}</p>
                    <p class="mt-1 font-bold" dir="ltr">{{ number_format($aiModelAccess->usage_count) }}</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3">
                    <p class="text-xs font-bold text-zinc-500">{{ __('ai_models.remaining_credit') }}</p>
                    <p class="mt-1 font-bold" dir="ltr">{{ $aiModelAccess->remainingCredit() === null ? __('ai_models.unlimited_credit') : number_format($aiModelAccess->remainingCredit()) }}</p>
                </div>
            </div>
        </x-admin.card>

        <x-admin.card>
            <div class="mb-4">
                <h3 class="text-base font-bold">{{ __('ai_models.edit') }}</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ __('ai_models.credential_placeholder') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.ai-models.update', $aiModelAccess) }}">
                @csrf
                @method('PUT')
                @include('admin.ai-models.form', [
                    'access' => $aiModelAccess,
                    'models' => $models,
                    'usageCategories' => $usageCategories,
                    'resolver' => $resolver,
                    'formKey' => 'edit_ai_model_access',
                ])
            </form>
        </x-admin.card>
    </div>
@endsection
