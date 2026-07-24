<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Provides template seeder behavior.
 *
 * @since 1.0.0
 */
class TemplateSeeder extends Seeder
{
    /**
     * Execute the template seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        Template::query()->updateOrCreate(
            ['code' => 'classic'],
            [
                'name' => __('messages.classic_template'),
                'is_active' => true,
            ]
        );
    }
}
