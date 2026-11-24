<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>> resource => actions
     */
    private const CATALOG = [
        'adjustment' => ['cancel', 'create', 'read'],
        'branch' => ['create', 'delete', 'read', 'update'],
        'category' => ['create', 'delete', 'read', 'update'],
        'dashboard' => ['read'],
        'handover' => ['cancel', 'complete', 'create', 'read', 'read_all', 'transfer'],
        'inventory' => ['read'],
        'movement' => ['create', 'read'],
        'permission' => ['read'],
        'product' => ['create', 'delete', 'read', 'update'],
        'rack' => ['create', 'delete', 'read', 'update'],
        'role' => ['create', 'delete', 'read', 'update'],
        'unit' => ['create', 'delete', 'read', 'update'],
        'user' => ['create', 'delete', 'read', 'update'],
        'warehouse' => ['create', 'delete', 'read', 'update'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$resource}.{$action}"]);
            }
        }
    }
}
