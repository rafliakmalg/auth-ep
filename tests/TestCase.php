<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static ?string $keyDirectory = null;

    private static function generateKeys(): string
    {
        $directory = sys_get_temp_dir().'/jwt-test-keys-'.getmypid();

        if (! is_dir($directory)) {
            mkdir($directory);
        }

        $options = ['config' => __DIR__.'/openssl.cnf'];

        foreach (['foreign', 'main'] as $name) {
            $key = openssl_pkey_new([
                ...$options,
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($key, $private, null, $options);

            file_put_contents("{$directory}/{$name}-private.pem", $private);
            file_put_contents("{$directory}/{$name}-public.pem", openssl_pkey_get_details($key)['key']);
        }

        return $directory;
    }

    protected function keyPath(string $name): string
    {
        self::$keyDirectory ??= self::generateKeys();

        return self::$keyDirectory."/{$name}.pem";
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.private_key_path' => $this->keyPath('main-private'),
            'jwt.public_key_path' => $this->keyPath('main-public'),
        ]);
    }

    protected function signIn(string ...$permissions): User
    {
        $role = Role::factory()->create();

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->withToken($user->issueToken());

        return $user;
    }
}
