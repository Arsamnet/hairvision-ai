@extends('admin.layout')

@section('title', __('leads.details_title'))
@section('page_title', __('leads.details_title'))

@section('content')
    @php
        $currentSourceImage = $lead->sourceImages->firstWhere('is_current', true);
        $primaryUpload = $lead->uploads->sortByDesc('created_at')->first();
        $independentGenerations = $lead->generationRequests;
        $notes = $lead->notes->sortByDesc('created_at');
    @endphp

    <div class="grid gap-5 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-teal-600">{{ $lead->tenant?->name }}</p>
                        <h2 class="mt-2 text-2xl font-bold">{{ $lead->full_name ?: __('leads.empty_name') }}</h2>
                        <p class="mt-2 text-sm text-zinc-500">{{ $lead->phone_normalized }}</p>
                    </div>
                    @can('update', $lead)
                        <x-admin.button :href="route('admin.leads.edit', $lead)" size="compact" icon="settings">
                            <span>{{ __('leads.edit') }}</span>
                        </x-admin.button>
                    @endcan
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-zinc-50 p-4">
                        <p class="text-xs font-bold text-zinc-500">{{ __('leads.fields.status') }}</p>
                        <p class="mt-2 font-bold">{{ __('leads.statuses.'.$lead->status) }}</p>
                    </div>
                    <div class="rounded-lg bg-teal-50 p-4">
                        <p class="text-xs font-bold text-teal-700">{{ __('leads.fields.crm_stage') }}</p>
                        <p class="mt-2 font-bold text-teal-900">{{ __('leads.crm_stages.'.$lead->crm_stage) }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4">
                        <p class="text-xs font-bold text-zinc-500">{{ __('leads.fields.source') }}</p>
                        <p class="mt-2 font-bold">{{ $lead->source ?: __('messages.none') }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4">
                        <p class="text-xs font-bold text-zinc-500">{{ __('leads.fields.owner') }}</p>
                        <p class="mt-2 font-bold">{{ $lead->assignedUser?->name ?: __('messages.none') }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-bold">{{ __('leads.uploads.title') }}</h3>
                    <x-admin.icon name="sparkles" class="text-teal-700" />
                </div>

                @if($currentSourceImage)
                    <figure class="mt-4 overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50">
                        <img src="{{ route('admin.leads.source-images.show', [$lead, $currentSourceImage]) }}" alt="{{ __('leads.source_images.alt') }}" class="max-h-128 w-full object-contain">
                        <figcaption class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-4 py-3 text-xs font-bold text-zinc-500">
                            <span>{{ __('leads.uploads.uploaded_at', ['date' => \App\Support\JalaliDate::toJalaliDateTime($currentSourceImage->created_at)]) }}</span>
                            <span>{{ __('leads.source_images.version', ['version' => $currentSourceImage->version]) }}</span>
                        </figcaption>
                    </figure>
                @elseif($primaryUpload)
                    <figure class="mt-4 overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50">
                        <img src="{{ route('admin.leads.uploads.show', [$lead, $primaryUpload]) }}" alt="{{ __('leads.uploads.alt') }}" class="max-h-128 w-full object-contain">
                        <figcaption class="border-t border-zinc-100 px-4 py-3 text-xs font-bold text-zinc-500">
                            {{ __('leads.uploads.uploaded_at', ['date' => \App\Support\JalaliDate::toJalaliDateTime($primaryUpload->created_at)]) }}
                        </figcaption>
                    </figure>
                @else
                    <div class="mt-4 rounded-lg border border-dashed border-zinc-200 bg-zinc-50 px-4 py-10 text-center">
                        <x-admin.empty-state icon="image">{{ __('leads.uploads.empty') }}</x-admin.empty-state>
                    </div>
                @endif
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <h3 class="text-lg font-bold">{{ __('leads.generations.title') }}</h3>
                <div class="mt-3 space-y-3">
                    @foreach($independentGenerations->groupBy(fn ($generation) => $generation->category?->name ?: __('leads.generations.unknown_category')) as $categoryName => $categoryGenerations)
                        <div class="rounded-lg border border-zinc-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-bold text-zinc-800">{{ $categoryName }}</h4>
                                <x-admin.badge tone="teal">{{ __('leads.generations.independent') }}</x-admin.badge>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($categoryGenerations as $generation)
                                    @php
                                        $asset = $generation->currentAsset;
                                        $wasRequested = $generation->requested_at !== null;
                                        $statusTone = match ($generation->status) {
                                            'completed' => 'emerald',
                                            'failed', 'cancelled' => 'rose',
                                            'processing', 'dispatched' => 'amber',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <article class="overflow-hidden rounded-lg bg-zinc-50 text-sm">
                                        @if($asset)
                                            <img src="{{ route('admin.leads.assets.show', [$lead, $asset]) }}" alt="{{ __('leads.generations.asset_alt', ['period' => $generation->output?->name ?: $asset->period]) }}" class="aspect-4/5 w-full bg-zinc-100 object-cover">
                                        @elseif($wasRequested)
                                            <div class="grid aspect-4/5 w-full place-items-center bg-zinc-100 px-3 text-center text-xs font-bold text-zinc-400">
                                                {{ __('leads.generations.asset_pending') }}
                                            </div>
                                        @else
                                            <div class="aspect-4/5 w-full bg-zinc-100" aria-hidden="true"></div>
                                        @endif
                                        <div class="space-y-2 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="font-bold">{{ $generation->output?->name ?: __('leads.generations.unknown_output') }}</p>
                                                @if($wasRequested)
                                                    <x-admin.badge :tone="$statusTone">
                                                        {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$generation->status) ? __('ai.generation.statuses.'.$generation->status) : $generation->status }}
                                                    </x-admin.badge>
                                                @endif
                                            </div>
                                            @if($wasRequested)
                                                <p class="text-xs text-zinc-500">
                                                    {{ __('leads.generations.requested_at', ['date' => \App\Support\JalaliDate::toJalaliDateTime($generation->requested_at)]) }}
                                                </p>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @foreach($lead->generationGroups as $group)
                        <div class="rounded-lg border border-zinc-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-bold">{{ __('leads.generations.group', ['uuid' => $group->uuid]) }}</span>
                                <span class="rounded-lg bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-700">{{ $group->status }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3">
                                @foreach($group->jobs as $job)
                                    @php
                                        $asset = $group->assets->firstWhere('generation_job_id', $job->id)
                                            ?? $group->assets->firstWhere('period', $job->period);
                                    @endphp
                                    <div class="overflow-hidden rounded-lg bg-zinc-50 text-sm">
                                        @if($asset)
                                            <img src="{{ route('admin.leads.assets.show', [$lead, $asset]) }}" alt="{{ __('leads.generations.asset_alt', ['period' => __('leads.periods.'.$job->period)]) }}" class="aspect-4/5 w-full bg-zinc-100 object-cover">
                                        @else
                                            <div class="grid aspect-4/5 w-full place-items-center bg-zinc-100 px-3 text-center text-xs font-bold text-zinc-400">
                                                {{ __('leads.generations.asset_pending') }}
                                            </div>
                                        @endif
                                        <div class="p-3">
                                            <p class="font-bold">{{ __('leads.periods.'.$job->period) }}</p>
                                            <p class="mt-1 text-zinc-500">{{ $job->status }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    @if($independentGenerations->isEmpty() && $lead->generationGroups->isEmpty())
                        <x-admin.empty-state icon="sparkles" class="py-5">{{ __('leads.generations.empty') }}</x-admin.empty-state>
                    @endif
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            @can('createNote', $lead)
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                    <h3 class="text-lg font-bold">{{ __('crm.new_follow_up') }}</h3>
                    <form method="POST" action="{{ route('admin.leads.follow-ups.store', $lead) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs font-bold text-zinc-500">{{ __('crm.status') }}</label>
                            <select name="status" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                                @foreach(\App\Support\StateCatalog::options('leads.crm_stages') as $stage => $label)
                                    <option value="{{ $stage }}" @selected($lead->crm_stage === $stage)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-500">{{ __('crm.channel') }}</label>
                            <select name="communication_channel" class="mt-1 h-11 w-full rounded-lg border border-zinc-200 px-3 text-sm outline-none transition focus:border-teal-500">
                                <option value="">{{ __('messages.select') }}</option>
                                @foreach(__('crm.channels') as $channel => $label)
                                    <option value="{{ $channel }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-500">{{ __('crm.next_follow_up_at') }}</label>
                            <div class="relative mt-1">
                                <x-admin.icon name="calendar" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input name="next_follow_up_at" type="text" value="{{ old('next_follow_up_at') }}" placeholder="{{ __('dates.placeholders.datetime') }}" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datetime class="h-11 w-full rounded-lg border border-zinc-200 py-0 pr-10 pl-3 text-sm outline-none transition focus:border-teal-500">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-bold text-zinc-500">{{ __('crm.note') }}</label>
                            <textarea name="note" rows="4" required placeholder="{{ __('crm.note_placeholder') }}" class="mt-1 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm leading-7 outline-none transition focus:border-teal-500"></textarea>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-bold text-zinc-600">
                            <input type="checkbox" name="is_completed" value="1" class="rounded border-zinc-300 text-teal-700">
                            <span>{{ __('crm.completed') }}</span>
                        </label>
                        <x-admin.button type="submit" size="compact-md" icon="message" block>
                            <span>{{ __('crm.submit') }}</span>
                        </x-admin.button>
                    </form>
                </section>
            @endcan

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-zinc-200">
                <h3 class="text-lg font-bold">{{ __('crm.timeline_title') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse($notes as $note)
                        <article class="relative rounded-lg border border-zinc-100 bg-zinc-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-teal-700">{{ __('leads.crm_stages.'.$note->status) }}</span>
                                @if($note->communication_channel)
                                    <span class="text-xs font-bold text-zinc-500">{{ __('crm.channels.'.$note->communication_channel) }}</span>
                                @endif
                            </div>
                            <p class="mt-3 text-sm leading-7 text-zinc-700">{{ $note->note }}</p>
                            <div class="mt-3 space-y-1 text-xs text-zinc-500">
                                <p>{{ $note->user?->name ?? __('leads.system_user') }} - <x-jalali-date-time :value="$note->created_at" /></p>
                                @if($note->next_follow_up_at)
                                    <p>{{ __('crm.next_follow_up_at') }}: <x-jalali-date-time :value="$note->next_follow_up_at" /></p>
                                @endif
                                @if($note->completed_at)
                                    <p class="font-bold text-emerald-700">{{ __('crm.completed') }}</p>
                                @endif
                            </div>
                            @can('updateNote', [$lead, $note])
                                @if(! $note->completed_at)
                                    <form method="POST" action="{{ route('admin.leads.follow-ups.update', [$lead, $note]) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_completed" value="1">
                                        <x-admin.button type="submit" variant="success-outline" size="compact-sm">{{ __('crm.mark_completed') }}</x-admin.button>
                                    </form>
                                @endif
                            @endcan
                        </article>
                    @empty
                        <x-admin.empty-state icon="message" class="py-5">{{ __('crm.empty') }}</x-admin.empty-state>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection
