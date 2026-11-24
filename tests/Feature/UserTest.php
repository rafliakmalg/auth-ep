<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_deletes_user_and_role_links(): void
    {
        $this->signIn('user.delete');
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create());

        $this->deleteJson("/api/users/{$user->id}")->assertOk()->assertJsonPath('message', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('role_user', ['user_id' => $user->id]);
    }

    public function test_guest_cannot_access_users(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_index_filters_by_role_and_search(): void
    {
        $this->signIn('user.read');
        $role = Role::factory()->create();
        $member = User::factory()->create(['name' => 'Budi Gudang']);
        $member->roles()->attach($role);
        User::factory()->create(['name' => 'Budi Lain']);

        $this->getJson('/api/users?search=budi&filters[0][field]=roles.id&filters[0][operator]=eq&filters[0][value]='.$role->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $member->id)
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_permission_is_required_for_every_action(): void
    {
        $this->signIn();
        $user = User::factory()->create();

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
        $this->putJson("/api/users/{$user->id}", [])->assertForbidden();
        $this->deleteJson("/api/users/{$user->id}")->assertForbidden();
    }

    public function test_show_returns_user_with_roles(): void
    {
        $this->signIn('user.read');
        $user = User::factory()->create();
        $user->roles()->attach(Role::factory()->create());

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonCount(1, 'data.roles');
    }

    public function test_store_creates_user_with_many_roles_and_hashed_password(): void
    {
        $actor = $this->signIn('user.create');
        $roles = Role::factory()->count(2)->create();

        $this->postJson('/api/users', [
            'name' => 'Budi',
            'password' => 'rahasia123',
            'role_ids' => $roles->pluck('id')->all(),
            'username' => 'budi',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pengguna berhasil ditambahkan.')
            ->assertJsonPath('data.created_by_id', $actor->id)
            ->assertJsonCount(2, 'data.roles');

        $user = User::where('username', 'budi')->firstOrFail();

        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(password_verify('rahasia123', $user->password));
    }

    public function test_store_rejects_invalid_input(): void
    {
        $this->signIn('user.create');
        User::factory()->create(['username' => 'budi']);

        $this->postJson('/api/users', [])->assertJsonValidationErrors(['name', 'password', 'role_ids', 'username']);
        $this->postJson('/api/users', [
            'name' => 'Budi',
            'password' => 'pendek',
            'role_ids' => [999, 999],
            'username' => 'budi',
        ])->assertJsonValidationErrors(['password', 'role_ids.0', 'username']);
    }

    public function test_update_replaces_roles_and_keeps_password_when_omitted(): void
    {
        $actor = $this->signIn('user.update');
        $user = User::factory()->create(['username' => 'budi']);
        $user->roles()->attach(Role::factory()->create());
        $replacement = Role::factory()->create();
        $before = $user->password;

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Budi Baru',
            'role_ids' => [$replacement->id],
            'username' => 'budi',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Baru')
            ->assertJsonPath('data.updated_by_id', $actor->id)
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonPath('data.roles.0.id', $replacement->id);

        $this->assertSame($before, $user->fresh()->password);
    }
}
