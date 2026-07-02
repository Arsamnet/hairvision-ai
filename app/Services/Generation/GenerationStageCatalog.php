<?php

namespace App\Services\Generation;

use App\Models\AiModelAccess;

/**
 * Central source for the three public hair-transplant generation stages.
 *
 * @since 1.0.0
 */
class GenerationStageCatalog
{
    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->stages());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function stages(): array
    {
        $stages = config('hairvision.generation.stages', []);

        uasort($stages, fn (array $first, array $second): int => ($first['order'] ?? 0) <=> ($second['order'] ?? 0));

        return $stages;
    }

    public function count(): int
    {
        return count($this->stages());
    }

    public function normalizeKey(string $stageKey): string
    {
        return config("hairvision.generation.legacy_stage_map.$stageKey", $stageKey);
    }

    public function order(string $stageKey): int
    {
        return (int) ($this->stages()[$this->normalizeKey($stageKey)]['order'] ?? 0);
    }

    public function label(string $stageKey): string
    {
        $stage = $this->stages()[$this->normalizeKey($stageKey)] ?? [];
        $labelKey = $stage['label_key'] ?? null;

        return is_string($labelKey) ? __($labelKey) : $stageKey;
    }

    public function caption(string $stageKey): string
    {
        return __('ai.generation.stage_captions.'.$this->normalizeKey($stageKey));
    }

    public function sampleImagePath(string $stageKey): ?string
    {
        $path = $this->stages()[$this->normalizeKey($stageKey)]['sample_image'] ?? null;

        return is_string($path) ? $path : null;
    }

    public function defaultPrompt(string $stageKey): string
    {
        $prompt = $this->stages()[$this->normalizeKey($stageKey)]['prompt'] ?? null;

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
    public function publicStages(): array
    {
        return collect($this->stages())
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
}
