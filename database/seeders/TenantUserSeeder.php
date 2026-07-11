<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantUserSeeder extends Seeder
{
    public function run(): void
    {
        $ariaTenant = Tenant::query()->where('slug', 'aria')->firstOrFail();

        foreach ([
            '09217359491' => 'clinic_admin',
            '09120000001' => 'editor',
            '09120000002' => 'viewer',
        ] as $phone => $role) {
            $user = User::query()->where('phone_normalized', $phone)->firstOrFail();

            $ariaTenant->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $role,
                    'status' => 'active',
                ],
            ]);

            $ariaTenant->users()->updateExistingPivot($user->id, [
                'role' => $role,
                'status' => 'active',
            ]);
        }

        $demoTenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        $demoAdmin = User::query()->where('phone_normalized', '09004666673')->firstOrFail();

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
