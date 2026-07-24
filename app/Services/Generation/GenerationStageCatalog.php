<?php

namespace App\Services\Generation;

use App\Models\AiModelCategoryOutput;

/**
 * Central source for public generation stages by service category.
 *
 * @since 1.0.0
 */
class GenerationStageCatalog
{
    /**
     * Execute the keys operation.
     *
     * @param  string  $usageCategoryKey  The usage category key value.
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    public function keys(string $usageCategoryKey = 'hair_transplant'): array
    {
        return array_keys($this->stages($usageCategoryKey));
    }

    /**
     * Execute the stages operation.
     *
     * @param  string  $usageCategoryKey  The usage category key value.
     * @return array<string, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function stages(string $usageCategoryKey = 'hair_transplant'): array
    {
        $allStages = config('hairvision.generation.stages', []);
        $serviceStageKeys = config("hairvision.generation.services.$usageCategoryKey.stages");
        $stages = [];

        if (is_array($serviceStageKeys) && $serviceStageKeys !== []) {
            foreach ($serviceStageKeys as $stageKey) {
                if (is_string($stageKey) && isset($allStages[$stageKey]) && is_array($allStages[$stageKey])) {
                    $stages[$stageKey] = $allStages[$stageKey];
                }
            }
        }

        if ($stages === [] && $usageCategoryKey === 'hair_transplant') {
            $stages = $allStages;
            unset($stages['eyebrow_final_result']);
        }

        uasort($stages, fn (array $first, array $second): int => ($first['order'] ?? 0) <=> ($second['order'] ?? 0));

        return $stages;
    }

    /**
     * Execute the count operation.
     *
     * @param  string  $usageCategoryKey  The usage category key value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function count(string $usageCategoryKey = 'hair_transplant'): int
    {
        return count($this->stages($usageCategoryKey));
    }

    /**
     * Normalize key.
     *
     * @param  string  $stageKey  The stage key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function normalizeKey(string $stageKey): string
    {
        return config("hairvision.generation.legacy_stage_map.$stageKey", $stageKey);
    }

    /**
     * Execute the order operation.
     *
     * @param  string  $stageKey  The stage key value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function order(string $stageKey): int
    {
        return (int) ($this->stage($stageKey)['order'] ?? 0);
    }

    /**
     * Execute the label operation.
     *
     * @param  string  $stageKey  The stage key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function label(string $stageKey): string
    {
        $stage = $this->stage($stageKey);
        $labelKey = $stage['label_key'] ?? null;

        return is_string($labelKey) ? __($labelKey) : $stageKey;
    }

    /**
     * Execute the caption operation.
     *
     * @param  string  $stageKey  The stage key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function caption(string $stageKey): string
    {
        return __('ai.generation.stage_captions.'.$this->normalizeKey($stageKey));
    }

    /**
     * Execute the sample image path operation.
     *
     * @param  string  $stageKey  The stage key value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function sampleImagePath(string $stageKey): ?string
    {
        $path = $this->stage($stageKey)['sample_image'] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * Execute the default prompt operation.
     *
     * @param  string  $stageKey  The stage key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function defaultPrompt(string $stageKey): string
    {
        $prompt = $this->stage($stageKey)['prompt'] ?? null;

        return is_string($prompt) ? $prompt : 'Create one realistic hair transplant preview image.';
    }

    /**
     * Execute the prompt for operation.
     *
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  string  $stageKey  The stage key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function promptFor(string $usageCategoryKey, string $stageKey): string
    {
        $stageKey = $this->normalizeKey($stageKey);

        $outputPrompt = AiModelCategoryOutput::query()
            ->where('code', $stageKey)
            ->whereHas('category', fn ($query) => $query->where('code', $usageCategoryKey))
            ->value('prompt');

        if (is_string($outputPrompt) && trim($outputPrompt) !== '') {
            return $outputPrompt;
        }

        return $this->defaultPrompt($stageKey);
    }

    /**
     * Execute the public stages operation.
     *
     * @param  string  $usageCategoryKey  The usage category key value.
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function publicStages(string $usageCategoryKey = 'hair_transplant'): array
    {
        return collect($this->stages($usageCategoryKey))
            ->map(fn (array $stage, string $key): array => [
                'key' => $key,
                'order' => (int) ($stage['order'] ?? 0),
                'label' => $this->label($key),
                'caption' => $this->caption($key),
            ])
            ->values()
            ->all();
    }

    /**
     * Execute the public stages for keys operation.
     *
     * @param  array<int, string>  $stageKeys
     * @return array<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function publicStagesForKeys(array $stageKeys): array
    {
        $uniqueStageKeys = collect($stageKeys)
            ->map(fn (string $stageKey): string => $this->normalizeKey($stageKey))
            ->unique()
            ->values()
            ->all();

        return collect($uniqueStageKeys)
            ->map(fn (string $key): array => [
                'key' => $key,
                'order' => $this->order($key),
                'label' => $this->label($key),
                'caption' => $this->caption($key),
            ])
            ->sortBy('order')
            ->values()
            ->all();
    }

    /**
     * Execute the sort by stage order operation.
     *
     * @param  array<int, object|array<string, mixed>>  $items
     * @return array<int, object|array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function sortByStageOrder(iterable $items): array
    {
        return collect($items)
            ->sortBy(fn (mixed $item): int => $this->order((string) (data_get($item, 'period') ?? data_get($item, 'key'))))
            ->values()
            ->all();
    }

    /**
     * Execute the stage operation.
     *
     * @param  string  $stageKey  The stage key value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function stage(string $stageKey): array
    {
        $stage = config('hairvision.generation.stages.'.$this->normalizeKey($stageKey), []);

        return is_array($stage) ? $stage : [];
    }
}
