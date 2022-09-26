<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RoleController extends Controller
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
        $roles = QueryBuilder::for(Role::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($roles);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $role)
    {
        $role = QueryBuilder::for(Role::class)
            ->whereKey($role)
            ->apply()
            ->first();

        if (!$role) throw (new ModelNotFoundException)->setModel(Role::class);

        return new Resource($role);
    }
}

