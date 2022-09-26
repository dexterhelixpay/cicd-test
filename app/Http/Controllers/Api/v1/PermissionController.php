<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Resources\Resource;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant');
        $this->middleware('permission:CP: User Management - View|MC: Users');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $permissions = QueryBuilder::for(Permission::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($permissions);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $permission)
    {
        $permission = QueryBuilder::for(Permission::class)
            ->whereKey($permission)
            ->apply()
            ->first();

        if (!$permission) throw (new ModelNotFoundException)->setModel(Permission::class);

        return new Resource($permission);
    }
}

