<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
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
