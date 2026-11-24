<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:role.read', only: ['index', 'show']),
            new Middleware('can:role.create', only: ['store']),
            new Middleware('can:role.update', only: ['update']),
            new Middleware('can:role.delete', only: ['destroy']),
        ];
    }

    public function index(IndexRoleRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Role::query()->with('permissions');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('label', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $roles = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return RoleResource::collection($roles);
    }

    public function store(RoleRequest $request): RoleResource
    {
        $param = $request->validated();

        $return = new RoleResource(null);

        DB::transaction(function () use ($param, $request, &$return) {
            $role = Role::create([
                ...Arr::except($param, ['permission_ids']),
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);

            $role->permissions()->sync($param['permission_ids']);
            $role->load('permissions');

            $message = __('index.created', ['resource' => __('index.role')]);

            $return = RoleResource::make($role)->additional(['message' => $message]);
        });

        return $return;
    }

    public function show(Role $role): RoleResource
    {
        $role->load('permissions');

        return RoleResource::make($role);
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $param = $request->validated();

        $return = new RoleResource(null);

        DB::transaction(function () use ($param, $request, $role, &$return) {
            $role->update([
                ...Arr::except($param, ['permission_ids']),
                'updated_by_id' => $request->user()->id,
            ]);

            $role->permissions()->sync($param['permission_ids']);
            $role->load('permissions');

            $message = __('index.updated', ['resource' => __('index.role')]);

            $return = RoleResource::make($role)->additional(['message' => $message]);
        });

        return $return;
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.role')])]);
    }
}
