<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Lang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const DUMMY_HASH = '$2y$12$nOlg6dRS.n3cVo7BVPhFSONMnqso3keNqkDpeI4YR/iwrDLl1EhzK';

    public function login(LoginRequest $request): JsonResponse
    {
        $param = $request->validated();

        $user = User::where('username', $param['username'])->first();

        // utk mencegah timing attack, kita tetap melakukan pengecekan password meskipun user tidak ditemukan
        $passwordMatches = Hash::check($param['password'], $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $user->active || ! $passwordMatches) {
            throw ValidationException::withMessages(['username' => __('index.login.failed')]);
        }

        $token = $user->issueToken();
        $permissions = $user->permissionNames();

        return response()->json([
            'data' => [
                'lang' => Lang::all(),
                'permissions' => $permissions,
                'token' => $token,
                'user' => UserResource::make($user),
            ],
        ]);
    }

    public function logout(): Response
    {
        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        $user = User::with('roles.permissions')->find($request->user()->id);

        abort_unless($user?->active, 401);

        $permissions = $user->permissionNames();

        return UserResource::make($user)->additional([
            'lang' => Lang::all(),
            'permissions' => $permissions,
        ]);
    }
}
