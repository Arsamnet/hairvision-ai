{{-- @since 2.1.0 Compacts source-centric processing history into responsive media rows. --}}
<div class="mt-3 space-y-3">
    @foreach($lead->sourceImages as $sourceImage)
        @php
            $sourceTitle = $sourceImage->is_current
                ? __('leads.gallery.current_source_title')
                : __('leads.gallery.source_title', ['version' => $sourceImage->version]);
            $orderedGenerations = $sourceImage->generationRequests
                ->sortBy(fn ($generation) => [
                    $generation->category?->sort_order ?? PHP_INT_MAX,
                    $generation->output?->sort_order ?? PHP_INT_MAX,
                    $generation->requested_at?->getTimestamp() ?? PHP_INT_MAX,
                    (string) $generation->id,
                ])
                ->values();
        @endphp

        <article class="lead-processing-round" data-processing-source-image="{{ $sourceImage->uuid }}">
            <header class="lead-processing-round-header">
                <h4 class="min-w-0 text-sm font-bold text-zinc-900">
                    {{ __('leads.generations.upload_round', ['version' => $sourceImage->version]) }}
                </h4>
                <x-admin.badge :tone="$sourceImage->is_current ? 'emerald' : 'zinc'">
                    {{ $sourceImage->is_current ? __('leads.source_images.current') : __('leads.source_images.archived') }}
                </x-admin.badge>
            </header>

            <div class="lead-processing-round-body">
                <div class="lead-processing-media-grid" data-processing-media-grid>
                    <figure
                        class="lead-processing-media-card"
                        data-processing-source-tile="source-{{ $sourceImage->uuid }}"
                    >
                        <x-admin.button
                            type="button"
                            variant="unstyled"
                            size="none"
                            class="lead-processing-media lead-gallery-trigger"
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
                        <figcaption class="lead-processing-media-caption">
                            <strong>{{ __('leads.generations.source_image') }}</strong>
                            <time datetime="{{ $sourceImage->created_at?->toIso8601String() }}">
                                {{ \App\Support\JalaliDate::toJalaliDateTime($sourceImage->created_at) }}
                            </time>
                        </figcaption>
                    </figure>

                    @foreach($orderedGenerations as $generation)
                        @php
                            $wasRequested = $generation->requested_at !== null;
                            $outputName = $generation->output?->name ?: __('leads.generations.unknown_output');
                            $statusLabel = \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$generation->status)
                                ? __('ai.generation.statuses.'.$generation->status)
                                : $generation->status;
                            $orderedAssets = $generation->assets
                                ->sortBy(fn ($asset) => [
                                    $asset->version ?? PHP_INT_MAX,
                                    $asset->created_at?->getTimestamp() ?? PHP_INT_MAX,
                                    (string) $asset->id,
                                ])
                                ->values();
                        @endphp

                        @forelse($orderedAssets as $asset)
                            @php
                                $assetDate = $asset->created_at ?? $generation->completed_at ?? $generation->requested_at;
                            @endphp
                            <figure
                                class="lead-processing-media-card"
                                data-processing-result-tile="asset-{{ $asset->uuid }}"
                                data-generation-result-source-image="{{ $sourceImage->uuid }}"
                            >
                                <x-admin.button
                                    type="button"
                                    variant="unstyled"
                                    size="none"
                                    class="lead-processing-media lead-gallery-trigger"
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
                                <figcaption class="lead-processing-media-caption">
                                    <strong>{{ $outputName }}</strong>
                                    @if($assetDate)
                                        <time datetime="{{ $assetDate->toIso8601String() }}">
                                            {{ \App\Support\JalaliDate::toJalaliDateTime($assetDate) }}
                                        </time>
                                    @endif
                                </figcaption>
                            </figure>
                        @empty
                            <figure
                                class="lead-processing-media-card"
                                data-processing-result-placeholder="generation-{{ $generation->uuid }}"
                            >
                                <div class="lead-processing-media lead-processing-media-placeholder">
                                    <x-admin.icon name="sparkles" class="h-5 w-5" />
                                    <span>{{ $wasRequested ? $statusLabel : __('leads.generations.not_requested') }}</span>
                                </div>
                                <figcaption class="lead-processing-media-caption">
                                    <strong>{{ $outputName }}</strong>
                                    @if($wasRequested)
                                        <time datetime="{{ $generation->requested_at->toIso8601String() }}">
                                            {{ \App\Support\JalaliDate::toJalaliDateTime($generation->requested_at) }}
                                        </time>
                                    @endif
                                </figcaption>
                            </figure>
                        @endforelse
                    @endforeach
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
            $groupStatusLabel = \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$group->status)
                ? __('ai.generation.statuses.'.$group->status)
                : $group->status;
        @endphp

        <article class="lead-processing-round" data-processing-upload="{{ $legacyUpload?->uuid }}">
            <header class="lead-processing-round-header">
                <h4 class="min-w-0 text-sm font-bold text-zinc-900">{{ __('leads.generations.legacy_upload') }}</h4>
                <x-admin.badge :tone="$groupStatusTone">{{ $groupStatusLabel }}</x-admin.badge>
            </header>

            <div class="lead-processing-round-body">
                <div class="lead-processing-media-grid" data-processing-media-grid>
                    <figure
                        class="lead-processing-media-card"
                        data-processing-source-tile="{{ $legacyUpload ? 'upload-'.$legacyUpload->uuid : 'group-'.$group->uuid }}"
                    >
                        @if($legacyUpload)
                            <x-admin.button
                                type="button"
                                variant="unstyled"
                                size="none"
                                class="lead-processing-media lead-gallery-trigger"
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
                        @else
                            <div class="lead-processing-media lead-processing-media-placeholder">
                                <x-admin.icon name="image" class="h-5 w-5" />
                                <span>{{ __('leads.uploads.empty') }}</span>
                            </div>
                        @endif
                        <figcaption class="lead-processing-media-caption">
                            <strong>{{ __('leads.generations.source_image') }}</strong>
                            @if($legacyUpload?->created_at)
                                <time datetime="{{ $legacyUpload->created_at->toIso8601String() }}">
                                    {{ \App\Support\JalaliDate::toJalaliDateTime($legacyUpload->created_at) }}
                                </time>
                            @endif
                        </figcaption>
                    </figure>

                    @foreach($group->jobs as $job)
                        @php
                            $asset = $group->assets->firstWhere('generation_job_id', $job->id)
                                ?? $group->assets->firstWhere('period', $job->period);
                            $jobStatusLabel = \Illuminate\Support\Facades\Lang::has('ai.generation.statuses.'.$job->status)
                                ? __('ai.generation.statuses.'.$job->status)
                                : $job->status;
                            $periodTitle = \Illuminate\Support\Facades\Lang::has('leads.periods.'.$job->period)
                                ? __('leads.periods.'.$job->period)
                                : $job->period;
                            $resultDate = $asset?->created_at ?? $job->completed_at ?? $job->created_at;
                        @endphp

                        <figure
                            class="lead-processing-media-card"
                            @if($asset)
                                data-processing-result-tile="asset-{{ $asset->uuid }}"
                                data-generation-result-upload="{{ $legacyUpload?->uuid }}"
                            @else
                                data-processing-result-placeholder="job-{{ $job->uuid }}"
                            @endif
                        >
                            @if($asset)
                                <x-admin.button
                                    type="button"
                                    variant="unstyled"
                                    size="none"
                                    class="lead-processing-media lead-gallery-trigger"
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
                            @else
                                <div class="lead-processing-media lead-processing-media-placeholder">
                                    <x-admin.icon name="sparkles" class="h-5 w-5" />
                                    <span>{{ $jobStatusLabel }}</span>
                                </div>
                            @endif
                            <figcaption class="lead-processing-media-caption">
                                <strong>{{ $periodTitle }}</strong>
                                @if($resultDate)
                                    <time datetime="{{ $resultDate->toIso8601String() }}">
                                        {{ \App\Support\JalaliDate::toJalaliDateTime($resultDate) }}
                                    </time>
                                @endif
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </article>
    @endforeach
</div>
