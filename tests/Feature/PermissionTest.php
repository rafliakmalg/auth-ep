<?php

namespace Tests\Feature;

use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_permissions(): void
    {
        $this->getJson('/api/permissions')->assertUnauthorized();
    }

    public function test_index_returns_every_permission_sorted_by_name(): void
    {
        $this->signIn('permission.read');
        Permission::factory()->create(['name' => 'a.read']);

        $this->getJson('/api/permissions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'a.read')
            ->assertJsonPath('data.1.name', 'permission.read');
    }

    public function test_index_requires_permission(): void
    {
        $this->signIn();

        $this->getJson('/api/permissions')->assertForbidden();
    }
}
