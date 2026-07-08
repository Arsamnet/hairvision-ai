<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
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
