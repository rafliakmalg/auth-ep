<?php

namespace App\Models;

use App\Contracts\Filterable;
use App\Support\Jwt;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['active', 'created_by_id', 'email', 'name', 'password', 'updated_by_id', 'username'])]
#[Hidden(['password'])]
class User extends Authenticatable implements Filterable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $attributes = [
        'active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function filterable(): array
    {
        return [
            'name' => 'string',
            'username' => 'string',
            'email' => 'string',
            'roles.id' => 'integer',
            'active' => 'boolean',
            'created_at' => 'date',
        ];
    }

    public function issueToken(): string
    {
        $this->loadMissing('roles.permissions');

        $now = time();

        $claims = [
            'sub' => $this->id,
            'name' => $this->name,
            'roles' => $this->roles->pluck('name')->sort()->values()->all(),
            'permissions' => $this->permissionNames(),
            'iat' => $now,
            'exp' => $now + config('jwt.ttl'),
        ];

        return Jwt::encode($claims);
    }

    /**
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
