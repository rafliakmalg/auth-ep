<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Jwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_token_stays_small_with_the_full_catalog(): void
    {
        $this->seed();

        $token = User::where('username', 'admin')->firstOrFail()->issueToken();

        $this->assertLessThan(4096, strlen($token));
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        config(['jwt.ttl' => -120]);

        $this->withToken($user->issueToken())->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guest_without_json_accept_header_gets_401_instead_of_a_server_error(): void
    {
        $this->get('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_rejects_inactive_user(): void
    {
        User::factory()->create([
            'username' => 'a',
            'active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'password',
        ])
            ->assertUnprocessable();
    }

    public function test_login_rejects_unknown_username(): void
    {
        $this->postJson('/api/auth/login', [
            'username' => 'x',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create(['username' => 'a']);

        $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'salah',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->postJson('/api/auth/login', [])->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_login_returns_token_with_union_of_permissions_from_all_roles(): void
    {
        $user = User::factory()->create(['username' => 'a']);
        $first = Role::factory()->create(['name' => 'first']);
        $second = Role::factory()->create(['name' => 'second']);
        $shared = Permission::factory()->create(['name' => 'shared.read']);
        $first->permissions()->attach([$shared->id, Permission::factory()->create(['name' => 'a.read'])->id]);
        $second->permissions()->attach([$shared->id, Permission::factory()->create(['name' => 'b.read'])->id]);
        $user->roles()->attach([$first->id, $second->id]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'a',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2, 'data.user.roles')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonPath('data.permissions', ['a.read', 'b.read', 'shared.read']);

        $claims = Jwt::decode($response->json('data.token'));

        $this->assertSame($user->id, $claims['sub']);
        $this->assertSame(['first', 'second'], $claims['roles']);
        $this->assertSame(['a.read', 'b.read', 'shared.read'], $claims['permissions']);
        $this->assertEqualsWithDelta(time() + 3600, $claims['exp'], 5);
    }

    public function test_logout_returns_no_content(): void
    {
        $this->signIn();

        $this->postJson('/api/auth/logout')->assertNoContent();
    }

    public function test_me_rejects_deactivated_user_even_with_a_valid_token(): void
    {
        $user = $this->signIn();
        $user->update(['active' => false]);

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user_with_roles_and_permissions(): void
    {
        $user = $this->signIn('product.read');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonCount(1, 'data.roles')
            ->assertJsonPath('permissions', ['product.read']);
    }

    public function test_seeded_users_can_login_with_the_default_password(): void
    {
        $this->seed();

        foreach (['admin', 'supervisor', 'operator'] as $role) {
            $this->postJson('/api/auth/login', [
                'username' => $role,
                'password' => 'admin123',
            ])
                ->assertOk()
                ->assertJsonPath('data.user.roles.0.name', $role);
        }
    }

    public function test_seeded_admin_has_every_permission_and_operator_has_no_access_management(): void
    {
        $this->seed();

        $total = Permission::count();

        $admin = $this->postJson('/api/auth/login', ['username' => 'admin', 'password' => 'admin123']);
        $operator = $this->postJson('/api/auth/login', ['username' => 'operator', 'password' => 'admin123']);

        $this->assertCount($total, $admin->json('data.permissions'));
        $this->assertContains('product.read', $operator->json('data.permissions'));
        $this->assertNotContains('user.read', $operator->json('data.permissions'));
        $this->assertNotContains('handover.read_all', $operator->json('data.permissions'));
        $this->assertNotContains('role.read', $operator->json('data.permissions'));
    }

    public function test_token_signed_with_another_key_is_rejected(): void
    {
        $user = User::factory()->create();
        $genuine = config('jwt.private_key_path');

        config(['jwt.private_key_path' => $this->keyPath('foreign-private')]);
        $forged = $user->issueToken();
        config(['jwt.private_key_path' => $genuine]);

        $this->withToken($forged)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
