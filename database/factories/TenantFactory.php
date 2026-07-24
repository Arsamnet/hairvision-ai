<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Provides tenant factory behavior.
 *
 * @extends Factory<Tenant>
 *
 * @since 1.0.0
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Execute the definition operation.
     *
     * @return array Resolved data array.
     *
     * @since 1.0.0
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'status' => 'active',
        ];
    }
}
