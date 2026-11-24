<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:user.read', only: ['index', 'show']),
            new Middleware('can:user.create', only: ['store']),
            new Middleware('can:user.update', only: ['update']),
            new Middleware('can:user.delete', only: ['destroy']),
        ];
    }

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = User::query()->with('roles');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('name', 'like', '%'.$param['search'].'%')
                    ->orWhere('username', 'like', '%'.$param['search'].'%')
                    ->orWhere('email', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $users = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): UserResource
    {
        $param = $request->validated();

        $return = new UserResource(null);

        DB::transaction(function () use ($param, $request, &$return) {
            $user = User::create([
                ...Arr::except($param, ['role_ids']),
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);

            $user->roles()->sync($param['role_ids']);
            $user->load('roles');

            $message = __('index.created', ['resource' => __('index.user')]);

            $return = UserResource::make($user)->additional(['message' => $message]);
        });

        return $return;
    }

    public function show(User $user): UserResource
    {
        $user->load('roles');

        return UserResource::make($user);
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $param = $request->validated();

        $return = new UserResource(null);

        DB::transaction(function () use ($param, $request, $user, &$return) {
            $user->update([
                ...Arr::except($param, ['role_ids']),
                'updated_by_id' => $request->user()->id,
            ]);

            $user->roles()->sync($param['role_ids']);
            $user->load('roles');

            $message = __('index.updated', ['resource' => __('index.user')]);

            $return = UserResource::make($user)->additional(['message' => $message]);
        });

        return $return;
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.user')])]);
    }
}
