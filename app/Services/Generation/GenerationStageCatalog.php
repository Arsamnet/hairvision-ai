<?php

namespace App\Services\Generation;

use App\Models\AiModelAccess;

/**
 * Central source for public generation stages by service category.
 *
 * @since 1.0.0
 */
class GenerationStageCatalog
{
    /**
     * @return array<int, string>
     */
    public function keys(string $usageCategoryKey = 'hair_transplant'): array
    {
        return array_keys($this->stages($usageCategoryKey));
    }

    /**
     * @return array<string, array<string, mixed>>
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

    public function count(string $usageCategoryKey = 'hair_transplant'): int
    {
        return count($this->stages($usageCategoryKey));
    }

    public function normalizeKey(string $stageKey): string
    {
        return config("hairvision.generation.legacy_stage_map.$stageKey", $stageKey);
    }

    public function order(string $stageKey): int
    {
        return (int) ($this->stage($stageKey)['order'] ?? 0);
    }

    public function label(string $stageKey): string
    {
        $stage = $this->stage($stageKey);
        $labelKey = $stage['label_key'] ?? null;

        return is_string($labelKey) ? __($labelKey) : $stageKey;
    }

    public function caption(string $stageKey): string
    {
        return __('ai.generation.stage_captions.'.$this->normalizeKey($stageKey));
    }

    public function sampleImagePath(string $stageKey): ?string
    {
        $path = $this->stage($stageKey)['sample_image'] ?? null;

        return is_string($path) ? $path : null;
    }

    public function defaultPrompt(string $stageKey): string
    {
        $prompt = $this->stage($stageKey)['prompt'] ?? null;

        return is_string($prompt) ? $prompt : 'Create one realistic hair transplant preview image.';
    }

    public function promptFor(AiModelAccess $access, string $stageKey): string
    {
        $stageKey = $this->normalizeKey($stageKey);
        $stagePrompt = data_get($access->settings ?? [], 'stage_prompts.'.$stageKey);

        if (is_string($stagePrompt) && trim($stagePrompt) !== '') {
            return $stagePrompt;
        }

        $basePrompt = trim((string) $access->prompt);
        $defaultPrompt = $this->defaultPrompt($stageKey);

        if ($basePrompt === '') {
            return $defaultPrompt;
        }

        if (! str_contains($basePrompt, '{stage_prompt}')) {
            return $basePrompt."\n\n".$defaultPrompt;
        }

        return strtr($basePrompt, [
            '{stage_key}' => $stageKey,
            '{stage_label}' => $this->label($stageKey),
            '{stage_prompt}' => $defaultPrompt,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
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
     * @param  array<int, string>  $stageKeys
     * @return array<int, array<string, mixed>>
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
     * @param  array<int, object|array<string, mixed>>  $items
     * @return array<int, object|array<string, mixed>>
     */
    public function sortByStageOrder(iterable $items): array
    {
        return collect($items)
            ->sortBy(fn (mixed $item): int => $this->order((string) (data_get($item, 'period') ?? data_get($item, 'key'))))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function stage(string $stageKey): array
    {
        $stage = config('hairvision.generation.stages.'.$this->normalizeKey($stageKey), []);

        return is_array($stage) ? $stage : [];
    }
}
