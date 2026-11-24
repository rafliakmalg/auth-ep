<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    private const ACCESS_RESOURCES = ['permission', 'role', 'user'];

    public function run(): void
    {
        $roles = [
            'admin' => 'Admin',
            'supervisor' => 'Supervisor',
            'operator' => 'Operator',
        ];

        $permissions = Permission::all();

        $operational = $permissions->reject(
            fn (Permission $permission) => in_array(Str::before($permission->name, '.'), self::ACCESS_RESOURCES, true),
        );

        foreach ($roles as $name => $label) {
            $role = Role::firstOrCreate(['name' => $name], ['label' => $label]);

            $granted = match ($name) {
                'admin' => $permissions,
                'operator' => $operational->reject(fn (Permission $permission) => $permission->name === 'handover.read_all'),
                default => $operational,
            };

            $role->permissions()->sync($granted->pluck('id'));
        }
    }
}
