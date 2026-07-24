<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

/**
 * Provides ai provider seeder behavior.
 *
 * @since 1.0.0
 */
class AiProviderSeeder extends Seeder
{
    /**
     * Execute the ai provider seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        AiProvider::query()->updateOrCreate(
            ['code' => 'sample_image'],
            [
                'name' => __('messages.sample_image_provider'),
                'type' => 'local',
                'is_active' => false,
                'supports_async' => false,
            ]
        );

        AiProvider::query()->updateOrCreate(
            ['code' => 'gapgpt'],
            [
                'name' => __('messages.gapgpt_image_provider'),
                'type' => 'external',
                'base_url' => config('ai-models.models.gapgpt.endpoint'),
                'auth_type' => 'bearer',
                'is_active' => true,
                'supports_async' => false,
                'default_model' => config('ai-models.models.gapgpt.default_model'),
            ]
        );
    }
}
