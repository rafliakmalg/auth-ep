<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'supervisor', 'operator'] as $role) {
            $user = User::firstOrCreate(['username' => $role], [
                'name' => 'Testing '.ucfirst($role),
                'password' => 'admin123',
            ]);

            $roleId = Role::where('name', $role)->value('id');

            $user->roles()->syncWithoutDetaching([$roleId]);
        }
    }
}
