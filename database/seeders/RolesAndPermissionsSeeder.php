<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            // Admin / request control
            'manage requests',
            'approve requests',
            'reject requests',
            'generate contracts',
            'sign admin contracts',
            'assign staff',
            'view reports',
            'view audit logs',

            // Staff / management
            'manage staff',
            'manage banks',
            'manage agents',
            'manage questions',
            'manage document steps',
            'manage contract templates',

            // Staff operational
            'view assigned requests',
            'add internal comments',
            'send request emails',
            'review documents',
            'update assigned workflow',

            // Client
            'create own request',
            'view own request',
            'upload own documents',
            'sign own contract',
            'update own profile',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $staffRole = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => $guard,
        ]);

        $clientRole = Role::firstOrCreate([
            'name' => 'client',
            'guard_name' => $guard,
        ]);

        $adminRole->syncPermissions($permissions);

        $staffRole->syncPermissions([
            'view assigned requests',
            'add internal comments',
            'send request emails',
            'review documents',
            'update assigned workflow',
        ]);

        $clientRole->syncPermissions([
            'create own request',
            'view own request',
            'upload own documents',
            'sign own contract',
            'update own profile',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}