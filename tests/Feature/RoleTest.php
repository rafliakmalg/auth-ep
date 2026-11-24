<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_deletes_unused_role_and_its_permission_links(): void
    {
        $this->signIn('role.delete');
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::factory()->create());

        $this->deleteJson("/api/roles/{$role->id}")->assertOk()->assertJsonPath('message', 'Role berhasil dihapus.');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id]);
    }

    public function test_destroy_returns_conflict_when_role_has_users(): void
    {
        $this->signIn('role.delete');
        $role = Role::factory()->create();
        User::factory()->create()->roles()->attach($role);

        $this->deleteJson("/api/roles/{$role->id}")->assertConflict();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_guest_cannot_access_roles(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
    }

    public function test_index_rejects_invalid_query(): void
    {
        $this->signIn('role.read');

        $this->getJson('/api/roles?per_page=1000&sort_by=id')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort_by']);
    }

    public function test_index_returns_paginated_roles_with_permissions_and_filters_by_search(): void
    {
        $this->signIn('role.read');
        $role = Role::factory()->create([
            'label' => 'Gudang',
            'name' => 'gudang',
        ]);
        $role->permissions()->attach(Permission::factory()->create(['name' => 'rack.read']));

        $this->getJson('/api/roles?search=gud')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'gudang')
            ->assertJsonPath('data.0.permissions.0.name', 'rack.read')
            ->assertJsonStructure(['data' => [['id', 'created_at', 'updated_at', 'created_by_id', 'updated_by_id', 'label', 'name', 'permissions']], 'links', 'meta']);
    }

    public function test_permission_is_checked_per_action(): void
    {
        $this->signIn('role.read');
        $role = Role::factory()->create();

        $this->getJson('/api/roles')->assertOk();
        $this->postJson('/api/roles', [
            'label' => 'X',
            'name' => 'x',
            'permission_ids' => [],
        ])->assertForbidden();
        $this->putJson("/api/roles/{$role->id}", [])->assertForbidden();
        $this->deleteJson("/api/roles/{$role->id}")->assertForbidden();
    }

    public function test_show_returns_role_with_permissions(): void
    {
        $this->signIn('role.read');
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::factory()->create());

        $this->getJson("/api/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonCount(1, 'data.permissions');
    }

    public function test_store_creates_role_with_audit_columns_and_permissions(): void
    {
        $user = $this->signIn('role.create');
        $permission = Permission::factory()->create();

        $this->postJson('/api/roles', [
            'label' => 'Supervisor',
            'name' => 'supervisor',
            'permission_ids' => [$permission->id],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Role berhasil ditambahkan.')
            ->assertJsonPath('data.name', 'supervisor')
            ->assertJsonPath('data.created_by_id', $user->id)
            ->assertJsonPath('data.updated_by_id', $user->id)
            ->assertJsonPath('data.permissions.0.id', $permission->id);
    }

    public function test_store_rejects_missing_duplicate_and_unknown_values(): void
    {
        $this->signIn('role.create');
        Role::factory()->create(['name' => 'gudang']);

        $this->postJson('/api/roles', [])->assertJsonValidationErrors(['label', 'name', 'permission_ids']);
        $this->postJson('/api/roles', [
            'label' => 'Gudang',
            'name' => 'gudang',
            'permission_ids' => [999],
        ])->assertJsonValidationErrors(['name', 'permission_ids.0']);
    }

    public function test_update_changes_role_and_replaces_permissions(): void
    {
        $user = $this->signIn('role.update');
        $role = Role::factory()->create(['name' => 'lama']);
        $role->permissions()->attach(Permission::factory()->create());
        $replacement = Permission::factory()->create();

        $this->putJson("/api/roles/{$role->id}", [
            'label' => 'Baru',
            'name' => 'lama',
            'permission_ids' => [$replacement->id],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Role berhasil diperbarui.')
            ->assertJsonPath('data.label', 'Baru')
            ->assertJsonPath('data.updated_by_id', $user->id)
            ->assertJsonCount(1, 'data.permissions')
            ->assertJsonPath('data.permissions.0.id', $replacement->id);
    }

    public function test_update_rejects_name_used_by_another_role(): void
    {
        $this->signIn('role.update');
        Role::factory()->create(['name' => 'milik-lain']);
        $role = Role::factory()->create(['name' => 'awal']);

        $this->putJson("/api/roles/{$role->id}", [
            'label' => 'Awal',
            'name' => 'milik-lain',
            'permission_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
