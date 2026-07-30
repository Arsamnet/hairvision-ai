<div class="mt-4 space-y-4">
    @foreach($lead->sourceImages as $sourceImage)
        @php
            $sourceGenerations = $sourceImage->generationRequests;
            $sourceTitle = $sourceImage->is_current
                ? __('leads.gallery.current_source_title')
                : __('leads.gallery.source_title', ['version' => $sourceImage->version]);
        @endphp

        <article class="lead-processing-round" data-processing-source-image="{{ $sourceImage->uuid }}">
            <header class="lead-processing-round-header">
                <div class="min-w-0">
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

            <div class="lead-processing-round-body">
                <figure class="lead-processing-source">
                    <x-admin.button
                        type="button"
                        variant="unstyled"
                        size="none"
                        class="lead-processing-source-media lead-gallery-trigger"
                        aria-label="{{ __('leads.gallery.open_image', ['title' => $sourceTitle]) }}"
                        data-lead-gallery-trigger="source-{{ $sourceImage->uuid }}"
                    >
                        <img
                            src="{{ route('admin.leads.source-images.show', [$lead, $sourceImage]) }}"
                            alt="{{ $sourceTitle }}"
                            loading="lazy"
                        >
                        <span class="lead-gallery-trigger-badge"><x-admin.icon name="zoom-in" class="h-4 w-4" /></span>
                    </x-admin.button>
                    <figcaption class="min-w-0">
                        <h5 class="text-sm font-bold text-zinc-800">{{ $sourceTitle }}</h5>
                        <p class="mt-1 text-xs leading-6 text-zinc-500">
                            {{ __('leads.gallery.source_description', ['date' => \App\Support\JalaliDate::toJalaliDateTime($sourceImage->created_at)]) }}
                        </p>
                        <p class="mt-2 text-xs font-bold text-teal-700">
                            {{ __('leads.generations.results_count', [
                                'count' => number_format($sourceGenerations->sum(fn ($generation) => $generation->assets->count())),
                            ]) }}
                        </p>
                    </figcaption>
                </figure>

                <div class="lead-processing-results space-y-4">
                    @forelse($sourceGenerations->groupBy(fn ($generation) => $generation->category?->name ?: __('leads.generations.unknown_category')) as $categoryName => $categoryGenerations)
                        <section class="lead-processing-category">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h5 class="text-sm font-bold text-zinc-800">{{ $categoryName }}</h5>
                                    <p class="mt-1 text-xs text-zinc-500">{{ __('leads.generations.results') }}</p>
                                </div>
                                <x-admin.badge tone="teal">{{ __('leads.generations.independent') }}</x-admin.badge>
                            </div>

                            <div class="lead-processing-results-grid mt-3">
                                @foreach($categoryGenerations as $generation)
                                    @php
                                        $wasRequested = $generation->requested_at !== null;
                                        $statusTone = match ($generation->status) {
                                            'completed' => 'emerald',
                                            'failed', 'cancelled' => 'rose',
                                            'processing', 'dispatched' => 'amber',
                                            default => 'zinc',
                                        };
                                        $outputName = $generation->output?->name ?: __('leads.generations.unknown_output');
                                    @endphp

                                    <article class="lead-processing-result-card text-sm">
                                        <div class="lead-processing-result-heading">
                                            <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                                                <h6 class="min-w-0 flex-1 font-bold text-zinc-900">{{ $outputName }}</h6>
                                                @if($wasRequested)
                                                    <x-admin.badge :tone="$statusTone">
                                                        {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$generation->status) ? __('ai.generation.statuses.'.$generation->status) : $generation->status }}
                                                    </x-admin.badge>
                                                @endif
                                            </div>
                                            @if($generation->output?->description)
                                                <p class="text-xs leading-6 text-zinc-500">{{ $generation->output->description }}</p>
                                            @endif
                                        </div>

                                        @if($generation->assets->isNotEmpty())
                                            <div class="lead-processing-assets">
                                                @foreach($generation->assets as $asset)
                                                    @php
                                                        $assetState = $asset->is_current
                                                            ? __('leads.generations.current_result')
                                                            : __('leads.generations.archived_result');
                                                    @endphp
                                                    <figure
                                                        class="overflow-hidden rounded-lg border border-zinc-100 bg-white"
                                                        data-generation-result-source-image="{{ $sourceImage->uuid }}"
                                                    >
                                                        <x-admin.button
                                                            type="button"
                                                            variant="unstyled"
                                                            size="none"
                                                            class="lead-processing-result-media lead-gallery-trigger"
                                                            aria-label="{{ __('leads.gallery.open_image', ['title' => $outputName]) }}"
                                                            data-lead-gallery-trigger="asset-{{ $asset->uuid }}"
                                                        >
                                                            <img
                                                                src="{{ route('admin.leads.assets.show', [$lead, $asset]) }}"
                                                                alt="{{ __('leads.generations.asset_alt', ['period' => $outputName]) }}"
                                                                loading="lazy"
                                                            >
                                                            <span class="lead-gallery-trigger-badge"><x-admin.icon name="zoom-in" class="h-4 w-4" /></span>
                                                        </x-admin.button>
                                                        <figcaption class="lead-processing-result-caption">
                                                            <span class="text-zinc-700">{{ $outputName }}</span>
                                                            <span>{{ __('leads.generations.result_version', ['version' => $asset->version]) }} · {{ $assetState }}</span>
                                                        </figcaption>
                                                    </figure>
                                                @endforeach
                                            </div>
                                        @elseif($wasRequested)
                                            <div class="lead-processing-empty-result">
                                                <span>
                                                    <x-admin.icon name="sparkles" class="mx-auto mb-2 h-5 w-5 text-teal-700" />
                                                    {{ __('leads.generations.asset_pending') }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="lead-processing-empty-result">
                                                {{ __('leads.generations.not_requested') }}
                                            </div>
                                        @endif

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
            $linkedSourceImage = $legacyUpload
                ? $lead->sourceImages->firstWhere('upload_id', $legacyUpload->id)
                : null;
            $legacyGalleryKey = $linkedSourceImage
                ? 'source-'.$linkedSourceImage->uuid
                : ($legacyUpload ? 'upload-'.$legacyUpload->uuid : null);
            $groupStatusTone = match ($group->status) {
                'completed' => 'emerald',
                'failed', 'cancelled' => 'rose',
                'processing' => 'amber',
                default => 'zinc',
            };
        @endphp

        <article class="lead-processing-round" data-processing-upload="{{ $legacyUpload?->uuid }}">
            <header class="lead-processing-round-header">
                <div class="min-w-0">
                    <h4 class="text-sm font-bold text-zinc-900">{{ __('leads.generations.legacy_upload') }}</h4>
                    <p class="mt-1 truncate text-xs text-zinc-500">
                        {{ __('leads.generations.group', ['uuid' => $group->uuid]) }}
                    </p>
                </div>
                <x-admin.badge :tone="$groupStatusTone">
                    {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$group->status) ? __('ai.generation.statuses.'.$group->status) : $group->status }}
                </x-admin.badge>
            </header>

            <div class="lead-processing-round-body">
                @if($legacyUpload)
                    <figure class="lead-processing-source">
                        <x-admin.button
                            type="button"
                            variant="unstyled"
                            size="none"
                            class="lead-processing-source-media lead-gallery-trigger"
                            aria-label="{{ __('leads.gallery.open_image', ['title' => __('leads.gallery.legacy_source_title')]) }}"
                            data-lead-gallery-trigger="{{ $legacyGalleryKey }}"
                        >
                            <img
                                src="{{ route('admin.leads.uploads.show', [$lead, $legacyUpload]) }}"
                                alt="{{ __('leads.gallery.legacy_source_title') }}"
                                loading="lazy"
                            >
                            <span class="lead-gallery-trigger-badge"><x-admin.icon name="zoom-in" class="h-4 w-4" /></span>
                        </x-admin.button>
                        <figcaption class="min-w-0">
                            <h5 class="text-sm font-bold text-zinc-800">{{ __('leads.gallery.legacy_source_title') }}</h5>
                            <p class="mt-1 text-xs leading-6 text-zinc-500">
                                {{ __('leads.gallery.legacy_source_description', ['date' => \App\Support\JalaliDate::toJalaliDateTime($legacyUpload->created_at)]) }}
                            </p>
                            <p class="mt-2 text-xs font-bold text-teal-700">
                                {{ __('leads.generations.results_count', ['count' => number_format($group->assets->count())]) }}
                            </p>
                        </figcaption>
                    </figure>
                @endif

                <section class="lead-processing-results">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h5 class="text-sm font-bold text-zinc-800">{{ __('leads.generations.results') }}</h5>
                        <span class="text-xs text-zinc-500">{{ __('leads.generations.results_count', ['count' => number_format($group->assets->count())]) }}</span>
                    </div>

                    <div class="lead-processing-results-grid mt-3">
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
                                $periodTitle = \Illuminate\Support\Facades\Lang::has('leads.periods.'.$job->period)
                                    ? __('leads.periods.'.$job->period)
                                    : $job->period;
                            @endphp

                            <article class="lead-processing-result-card text-sm">
                                <div class="lead-processing-result-heading">
                                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                                        <h6 class="min-w-0 flex-1 font-bold text-zinc-900">{{ $periodTitle }}</h6>
                                        <x-admin.badge :tone="$jobStatusTone">
                                            {{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$job->status) ? __('ai.generation.statuses.'.$job->status) : $job->status }}
                                        </x-admin.badge>
                                    </div>
                                </div>

                                @if($asset)
                                    <div class="lead-processing-assets">
                                        <figure
                                            class="overflow-hidden rounded-lg border border-zinc-100 bg-white"
                                            data-generation-result-upload="{{ $legacyUpload?->uuid }}"
                                        >
                                            <x-admin.button
                                                type="button"
                                                variant="unstyled"
                                                size="none"
                                                class="lead-processing-result-media lead-gallery-trigger"
                                                aria-label="{{ __('leads.gallery.open_image', ['title' => $periodTitle]) }}"
                                                data-lead-gallery-trigger="asset-{{ $asset->uuid }}"
                                            >
                                                <img
                                                    src="{{ route('admin.leads.assets.show', [$lead, $asset]) }}"
                                                    alt="{{ __('leads.generations.asset_alt', ['period' => $periodTitle]) }}"
                                                    loading="lazy"
                                                >
                                                <span class="lead-gallery-trigger-badge"><x-admin.icon name="zoom-in" class="h-4 w-4" /></span>
                                            </x-admin.button>
                                            <figcaption class="lead-processing-result-caption">
                                                <span class="text-zinc-700">{{ $periodTitle }}</span>
                                                <span>{{ \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$job->status) ? __('ai.generation.statuses.'.$job->status) : $job->status }}</span>
                                            </figcaption>
                                        </figure>
                                    </div>
                                @else
                                    <div class="lead-processing-empty-result">{{ __('leads.generations.asset_pending') }}</div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            </div>
        </article>
    @endforeach
</div>
