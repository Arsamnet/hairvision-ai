<?php

namespace App\View\Admin;

use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\Lead;
use App\Models\LeadSourceImage;
use App\Models\Upload;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Builds the ordered, private image sequence shown in the lead gallery.
 *
 * @since 2.0.0
 */
final class LeadImageGalleryPresenter
{
    /**
     * Build gallery items from the relationships already authorized for a lead detail page.
     *
     * @param  Lead  $lead  Loaded tenant-scoped lead.
     * @return array<int, array{key: string, url: string, title: string, description: string, fileName: string}>
     *
     * @since 2.0.0
     */
    public function items(Lead $lead): array
    {
        $items = [];
        $keys = [];

        foreach ($this->rounds($lead) as $round) {
            if ($round['type'] === 'source') {
                $sourceImage = $round['record'];
                $this->appendSourceRound($items, $keys, $lead, $sourceImage);

                if ($sourceImage->upload_id) {
                    $this->appendLegacyAssets(
                        $items,
                        $keys,
                        $lead,
                        $lead->generationGroups->where('upload_id', $sourceImage->upload_id),
                    );
                }

                continue;
            }

            if ($round['type'] === 'upload') {
                $upload = $round['record'];
                $this->appendUpload($items, $keys, $lead, $upload);
                $this->appendLegacyAssets(
                    $items,
                    $keys,
                    $lead,
                    $lead->generationGroups->where('upload_id', $upload->id),
                );

                continue;
            }

            $this->appendLegacyAssets($items, $keys, $lead, collect([$round['record']]));
        }

        return $items;
    }

    /**
     * Merge source-image and legacy-upload rounds in reverse chronological order.
     *
     * @param  Lead  $lead  Loaded tenant-scoped lead.
     * @return Collection<int, array{type: string, record: LeadSourceImage|Upload|GenerationGroup, created_at: mixed}>
     *
     * @since 2.0.0
     */
    private function rounds(Lead $lead): Collection
    {
        $linkedUploadIds = $lead->sourceImages
            ->pluck('upload_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();
        $rounds = collect();

        foreach ($lead->sourceImages as $sourceImage) {
            $rounds->push([
                'type' => 'source',
                'record' => $sourceImage,
                'created_at' => $sourceImage->created_at,
            ]);
        }

        foreach ($lead->uploads as $upload) {
            if (! in_array((int) $upload->id, $linkedUploadIds, true)) {
                $rounds->push([
                    'type' => 'upload',
                    'record' => $upload,
                    'created_at' => $upload->created_at,
                ]);
            }
        }

        foreach ($lead->generationGroups->whereNull('upload_id') as $group) {
            $rounds->push([
                'type' => 'orphan_group',
                'record' => $group,
                'created_at' => $group->created_at,
            ]);
        }

        return $rounds
            ->sortByDesc(fn (array $round): int => $round['created_at']?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Append one versioned source image and each result generated from it.
     *
     * @param  array<int, array{key: string, url: string, title: string, description: string, fileName: string}>  $items
     * @param  array<string, true>  $keys
     * @param  Lead  $lead  Loaded tenant-scoped lead.
     * @param  LeadSourceImage  $sourceImage  Source image represented by this gallery round.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function appendSourceRound(array &$items, array &$keys, Lead $lead, LeadSourceImage $sourceImage): void
    {
        $sourceTitle = $sourceImage->is_current
            ? __('leads.gallery.current_source_title')
            : __('leads.gallery.source_title', ['version' => $sourceImage->version]);

        $this->append(
            $items,
            $keys,
            'source-'.$sourceImage->uuid,
            route('admin.leads.source-images.show', [$lead, $sourceImage]),
            $sourceTitle,
            __('leads.gallery.source_description', [
                'date' => JalaliDate::toJalaliDateTime($sourceImage->created_at),
            ]),
            $sourceImage->mime,
        );

        foreach ($sourceImage->generationRequests as $generation) {
            foreach ($generation->assets as $asset) {
                $outputName = $generation->output?->name ?: __('leads.generations.unknown_output');
                $categoryName = $generation->category?->name ?: __('leads.generations.unknown_category');
                $assetState = $asset->is_current
                    ? __('leads.generations.current_result')
                    : __('leads.generations.archived_result');
                $description = collect([
                    $generation->output?->description,
                    __('leads.gallery.generated_description', [
                        'category' => $categoryName,
                        'version' => __('leads.generations.result_version', ['version' => $asset->version]),
                        'state' => $assetState,
                    ]),
                ])->filter()->implode(' • ');

                $this->appendAsset($items, $keys, $lead, $asset, $outputName, $description);
            }
        }
    }

    /**
     * Append one legacy upload before the results generated from it.
     *
     * @param  array<int, array{key: string, url: string, title: string, description: string, fileName: string}>  $items
     * @param  array<string, true>  $keys
     * @param  Lead  $lead  Loaded tenant-scoped lead.
     * @param  Upload  $upload  Private visitor upload.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function appendUpload(array &$items, array &$keys, Lead $lead, Upload $upload): void
    {
        $this->append(
            $items,
            $keys,
            'upload-'.$upload->uuid,
            route('admin.leads.uploads.show', [$lead, $upload]),
            __('leads.gallery.legacy_source_title'),
            __('leads.gallery.legacy_source_description', [
                'date' => JalaliDate::toJalaliDateTime($upload->created_at),
            ]),
            $upload->mime,
        );
    }

    /**
     * Append generated assets from one or more legacy generation groups.
     *
     * @param  array<int, array{key: string, url: string, title: string, description: string, fileName: string}>  $items
     * @param  array<string, true>  $keys
     * @param  Lead  $lead  Loaded tenant-scoped lead.
     * @param  Collection<int, GenerationGroup>  $groups  Legacy groups belonging to the lead.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function appendLegacyAssets(array &$items, array &$keys, Lead $lead, Collection $groups): void
    {
        foreach ($groups as $group) {
            foreach ($group->assets as $asset) {
                $job = $group->jobs->firstWhere('id', $asset->generation_job_id)
                    ?? $group->jobs->firstWhere('period', $asset->period);
                $periodKey = $job?->period ?: $asset->period;
                $periodTitle = Lang::has('leads.periods.'.$periodKey)
                    ? __('leads.periods.'.$periodKey)
                    : $periodKey;
                $status = $job?->status ?: $group->status;
                $statusLabel = Lang::has('ai.generation.statuses.'.$status)
                    ? __('ai.generation.statuses.'.$status)
                    : $status;

                $this->appendAsset(
                    $items,
                    $keys,
                    $lead,
                    $asset,
                    $periodTitle,
                    __('leads.gallery.legacy_generated_description', ['state' => $statusLabel]),
                );
            }
        }
    }

    /**
     * Append one generated asset using its authorized dashboard stream URL.
     *
     * @param  array<int, array{key: string, url: string, title: string, description: string, fileName: string}>  $items
     * @param  array<string, true>  $keys
     * @param  Lead  $lead  Loaded tenant-scoped lead.
     * @param  GeneratedAsset  $asset  Generated image asset.
     * @param  string  $title  User-facing image title.
     * @param  string  $description  User-facing image description.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function appendAsset(
        array &$items,
        array &$keys,
        Lead $lead,
        GeneratedAsset $asset,
        string $title,
        string $description,
    ): void {
        $this->append(
            $items,
            $keys,
            'asset-'.$asset->uuid,
            route('admin.leads.assets.show', [$lead, $asset]),
            $title,
            $description,
            $asset->mime,
        );
    }

    /**
     * Add one unique gallery item and derive a safe download filename from its MIME type.
     *
     * @param  array<int, array{key: string, url: string, title: string, description: string, fileName: string}>  $items
     * @param  array<string, true>  $keys
     * @param  string  $key  Stable client-side gallery key.
     * @param  string  $url  Authorized private stream URL.
     * @param  string  $title  User-facing image title.
     * @param  string  $description  User-facing image description.
     * @param  string|null  $mime  Stored safe image MIME type.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function append(
        array &$items,
        array &$keys,
        string $key,
        string $url,
        string $title,
        string $description,
        ?string $mime,
    ): void {
        if (isset($keys[$key])) {
            return;
        }

        $extension = match (strtolower((string) $mime)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $keys[$key] = true;
        $items[] = [
            'key' => $key,
            'url' => $url,
            'title' => $title,
            'description' => $description,
            'fileName' => 'hairvision-lead-'.$key.'.'.$extension,
        ];
    }
}
