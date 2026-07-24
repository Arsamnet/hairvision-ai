<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Support\LandingTemplateDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function (): void {
            $template = Template::query()->firstOrCreate(
                ['code' => LandingTemplateDefaults::CODE],
                array_merge(LandingTemplateDefaults::content(), [
                    'name' => __('messages.classic_template'),
                    'is_active' => true,
                    'is_default' => true,
                ])
            );

            if (! Template::query()->where('is_default', true)->exists()) {
                $template->forceFill([
                    'is_active' => true,
                    'is_default' => true,
                ])->save();
            }
        });
    }
}
