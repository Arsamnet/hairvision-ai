<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Auth\PermissionCatalog;
use App\Services\Auth\PermissionService;
use Illuminate\Database\Seeder;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = app(PermissionCatalog::class);

        foreach ($catalog->permissions() as $group => $permissions) {
            foreach ($permissions as $permission) {
                Permission::query()->updateOrCreate(
                    ['code' => $permission['code']],
                    [
                        'name' => $permission['name'],
                        'group' => $group,
                        'description' => $permission['description'] ?? null,
                    ]
                );
            }
        }

        foreach ($catalog->roles() as $code => $roleDefinition) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $roleDefinition['name'],
                    'description' => $roleDefinition['description'],
                    'scope' => $roleDefinition['scope'],
                    'is_system' => true,
                    'status' => 'active',
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('code', $roleDefinition['permissions'], 'and', false)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
            app(PermissionService::class)->forgetRole($role->code);
        }
    }
}
