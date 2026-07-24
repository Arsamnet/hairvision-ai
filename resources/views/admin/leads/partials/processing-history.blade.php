<div class="mt-3 space-y-4">
    @foreach($lead->sourceImages as $sourceImage)
        @php
            $sourceGenerations = $sourceImage->generationRequests;
        @endphp

        <article
            class="overflow-hidden rounded-lg border border-zinc-200"
            data-processing-source-image="{{ $sourceImage->uuid }}"
        >
            <header class="flex flex-wrap items-center justify-between gap-3 bg-zinc-50 px-4 py-3">
                <div>
                    <h4 class="text-sm font-bold text-zinc-900">
                        {{ __('leads.generations.upload_round', ['version' => $sourceImage->version]) }}
                    </h4>
                    <p class="mt-1 text-xs text-zinc-500">
                        {{ __('leads.uploads.uploaded_at', ['date' => \App\Support\JalaliDate::toJalaliDateTime($sourceImage->created_at)]) }}
                    </p>
                </div>
                <x-admin.badge :tone="$sourceImage->is_current ? 'emerald' : 'zinc'">
                    {{ $sourceImage->is_current ? __('leads.source_images.current') : __('leads.source_images.archived') }}
                </x-admin.badge>
            </header>

            <div class="grid gap-4 p-4 lg:grid-cols-[14rem_minmax(0,1fr)]">
                <figure class="overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50">
                    <img
                        src="{{ route('admin.leads.source-images.show', [$lead, $sourceImage]) }}"
                        alt="{{ __('leads.generations.source_image') }}"
                        class="aspect-4/5 w-full object-cover"
                    >
                    <figcaption class="border-t border-zinc-100 px-3 py-2 text-xs font-bold text-zinc-600">
                        {{ __('leads.generations.source_image') }}
                    </figcaption>
                </figure>

                <div class="min-w-0 space-y-3">
                    <h5 class="text-sm font-bold text-zinc-800">{{ __('leads.generations.results') }}</h5>

                    @forelse($sourceGenerations->groupBy(fn ($generation) => $generation->category?->name ?: __('leads.generations.unknown_category')) as $categoryName => $categoryGenerations)
                        <section class="rounded-lg border border-zinc-100 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <h6 class="text-sm font-bold text-zinc-800">{{ $categoryName }}</h6>
                                <x-admin.badge tone="teal">{{ __('leads.generations.independent') }}</x-admin.badge>
                            </div>

                            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($categoryGenerations as $generation)
                                    @php
                                        $wasRequested = $generation->requested_at !== null;
                                        $statusTone = match ($generation->status) {
                                            'completed' => 'emerald',
                                            'failed', 'cancelled' => 'rose',
                                            'processing', 'dispatched' => 'amber',
                                            default => 'zinc',
                                        };
                                    @endphp

                                    <article class="overflow-hidden rounded-lg bg-zinc-50 text-sm">
                                        <div class="flex items-start justify-between gap-2 p-3">
                                            <p class="font-bold">
                                                {{ $generation->output?->name ?: __('leads.generations.unknown_output') }}
                                            </p>
                                            @if($wasRequested)
                                                <x-admin.badge :tone="$statusTone">
                                                    {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$generation->status) ? __('ai.generation.statuses.'.$generation->status) : $generation->status }}
                                                </x-admin.badge>
                                            @endif
                                        </div>

                                        <div class="grid gap-2 px-3 pb-3">
                                            @forelse($generation->assets as $asset)
                                                <figure
                                                    class="overflow-hidden rounded-lg border border-zinc-100 bg-white"
                                                    data-generation-result-source-image="{{ $sourceImage->uuid }}"
                                                >
                                                    <img
                                                        src="{{ route('admin.leads.assets.show', [$lead, $asset]) }}"
                                                        alt="{{ __('leads.generations.asset_alt', ['period' => $generation->output?->name ?: $asset->period]) }}"
                                                        class="aspect-4/5 w-full bg-zinc-100 object-cover"
                                                    >
                                                    <figcaption class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-2 py-2 text-[0.7rem] font-bold text-zinc-500">
                                                        <span>{{ __('leads.generations.result_version', ['version' => $asset->version]) }}</span>
                                                        <span>{{ $asset->is_current ? __('leads.generations.current_result') : __('leads.generations.archived_result') }}</span>
                                                    </figcaption>
                                                </figure>
                                            @empty
                                                @if($wasRequested)
                                                    <div class="grid aspect-4/5 w-full place-items-center rounded-lg bg-zinc-100 px-3 text-center text-xs font-bold text-zinc-400">
                                                        {{ __('leads.generations.asset_pending') }}
                                                    </div>
                                                @else
                                                    <div class="aspect-4/5 w-full rounded-lg bg-zinc-100" aria-hidden="true"></div>
                                                @endif
                                            @endforelse
                                        </div>

                                        @if($wasRequested)
                                            <p class="border-t border-zinc-100 px-3 py-2 text-xs text-zinc-500">
                                                {{ __('leads.generations.requested_at', ['date' => \App\Support\JalaliDate::toJalaliDateTime($generation->requested_at)]) }}
                                            </p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @empty
                        <x-admin.empty-state icon="sparkles" class="py-5">
                            {{ __('leads.generations.no_results') }}
                        </x-admin.empty-state>
                    @endforelse
                </div>
            </div>
        </article>
    @endforeach

    @foreach($lead->generationGroups as $group)
        @php
            $legacyUpload = $group->upload;
            $groupStatusTone = match ($group->status) {
                'completed' => 'emerald',
                'failed', 'cancelled' => 'rose',
                'processing' => 'amber',
                default => 'zinc',
            };
        @endphp

        <article
            class="overflow-hidden rounded-lg border border-zinc-200"
            data-processing-upload="{{ $legacyUpload?->uuid }}"
        >
            <header class="flex flex-wrap items-center justify-between gap-3 bg-zinc-50 px-4 py-3">
                <div>
                    <h4 class="text-sm font-bold text-zinc-900">{{ __('leads.generations.legacy_upload') }}</h4>
                    <p class="mt-1 text-xs text-zinc-500">
                        {{ __('leads.generations.group', ['uuid' => $group->uuid]) }}
                    </p>
                </div>
                <x-admin.badge :tone="$groupStatusTone">
                    {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$group->status) ? __('ai.generation.statuses.'.$group->status) : $group->status }}
                </x-admin.badge>
            </header>

            <div class="grid gap-4 p-4 lg:grid-cols-[14rem_minmax(0,1fr)]">
                @if($legacyUpload)
                    <figure class="overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50">
                        <img
                            src="{{ route('admin.leads.uploads.show', [$lead, $legacyUpload]) }}"
                            alt="{{ __('leads.generations.source_image') }}"
                            class="aspect-4/5 w-full object-cover"
                        >
                        <figcaption class="border-t border-zinc-100 px-3 py-2 text-xs font-bold text-zinc-600">
                            {{ __('leads.generations.source_image') }}
                        </figcaption>
                    </figure>
                @endif

                <div class="min-w-0">
                    <h5 class="text-sm font-bold text-zinc-800">{{ __('leads.generations.results') }}</h5>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($group->jobs as $job)
                            @php
                                $asset = $group->assets->firstWhere('generation_job_id', $job->id)
                                    ?? $group->assets->firstWhere('period', $job->period);
                                $jobStatusTone = match ($job->status) {
                                    'completed' => 'emerald',
                                    'failed', 'cancelled' => 'rose',
                                    'processing' => 'amber',
                                    default => 'zinc',
                                };
                            @endphp

                            <article class="overflow-hidden rounded-lg bg-zinc-50 text-sm">
                                @if($asset)
                                    <img
                                        src="{{ route('admin.leads.assets.show', [$lead, $asset]) }}"
                                        alt="{{ __('leads.generations.asset_alt', ['period' => __('leads.periods.'.$job->period)]) }}"
                                        class="aspect-4/5 w-full bg-zinc-100 object-cover"
                                        data-generation-result-upload="{{ $legacyUpload?->uuid }}"
                                    >
                                @else
                                    <div class="grid aspect-4/5 w-full place-items-center bg-zinc-100 px-3 text-center text-xs font-bold text-zinc-400">
                                        {{ __('leads.generations.asset_pending') }}
                                    </div>
                                @endif
                                <div class="flex items-start justify-between gap-2 p-3">
                                    <p class="font-bold">{{ __('leads.periods.'.$job->period) }}</p>
                                    <x-admin.badge :tone="$jobStatusTone">
                                        {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$job->status) ? __('ai.generation.statuses.'.$job->status) : $job->status }}
                                    </x-admin.badge>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </article>
    @endforeach

</div>
