<?php

namespace Database\Factories;

use App\Models\PlatformUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provides platform user factory behavior.
 *
 * @extends Factory<PlatformUser>
 *
 * @since 1.0.0
 */
class PlatformUserFactory extends Factory
{
    protected $model = PlatformUser::class;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_normalized' => fake()->unique()->numerify('091########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
}
