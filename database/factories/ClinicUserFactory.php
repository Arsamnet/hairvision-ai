<?php

namespace Database\Factories;

use App\Models\ClinicUser;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provides clinic user factory behavior.
 *
 * @extends Factory<ClinicUser>
 *
 * @since 1.0.0
 */
class ClinicUserFactory extends Factory
{
    protected $model = ClinicUser::class;

    protected static ?string $password;

    /**
     * Execute the definition operation.
     *
     * @return array Resolved data array.
     *
     * @since 1.0.0
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_normalized' => fake()->unique()->numerify('091########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'viewer',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
}
