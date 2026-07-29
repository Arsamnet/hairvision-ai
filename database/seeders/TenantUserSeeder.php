<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Provides tenant user seeder behavior.
 *
 * @since 1.0.0
 */
class TenantUserSeeder extends Seeder
{
    /**
     * Execute the tenant user seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        $aryaTenant = Tenant::query()->where('slug', 'arya')->firstOrFail();

        foreach ([
            '09217359491' => 'clinic_admin',
            '09120000001' => 'editor',
            '09120000002' => 'viewer',
        ] as $phone => $role) {
            $user = User::query()->where('phone_normalized', $phone)->firstOrFail();

            $aryaTenant->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $role,
                    'status' => 'active',
                ],
            ]);

            $aryaTenant->users()->updateExistingPivot($user->id, [
                'role' => $role,
                'status' => 'active',
            ]);
        }

        $demoTenant = Tenant::query()->where('slug', 'demo')->firstOrFail();

        foreach (['09004666673', '09127072327'] as $phone) {
            $demoAdmin = User::query()->where('phone_normalized', $phone)->firstOrFail();

            $demoTenant->users()->syncWithoutDetaching([
                $demoAdmin->id => [
                    'role' => 'clinic_admin',
                    'status' => 'active',
                ],
            ]);

            $demoTenant->users()->updateExistingPivot($demoAdmin->id, [
                'role' => 'clinic_admin',
                'status' => 'active',
            ]);
        }

        $silverSandsTenant = Tenant::query()->where('slug', 'silversands')->firstOrFail();
        $silverSandsAdmin = User::query()->where('phone_normalized', '09121012193')->firstOrFail();

        $silverSandsTenant->users()->syncWithoutDetaching([
            $silverSandsAdmin->id => [
                'role' => 'clinic_admin',
                'status' => 'active',
            ],
        ]);

        $silverSandsTenant->users()->updateExistingPivot($silverSandsAdmin->id, [
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);
    }
}
