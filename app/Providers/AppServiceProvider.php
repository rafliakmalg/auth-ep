<?php

namespace App\Providers;

use App\Support\Jwt;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('jwt', function (Request $request) {
            $token = $request->bearerToken();

            if (! $token) {
                return null;
            }

            try {
                $claims = Jwt::decode($token);
            } catch (Throwable) {
                return null;
            }

            return new GenericUser([
                'id' => $claims['sub'],
                'name' => $claims['name'],
                'permissions' => $claims['permissions'],
                'roles' => $claims['roles'],
            ]);
        });

        Gate::before(fn ($user, string $ability) => in_array($ability, $user->permissions, true));
    }
}
