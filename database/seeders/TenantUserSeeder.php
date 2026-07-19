<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantUserSeeder extends Seeder
{
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
    }
}
