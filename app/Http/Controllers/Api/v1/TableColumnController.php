<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\TableColumn;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TableColumnController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $columns = QueryBuilder::for(TableColumn::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($columns);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $column
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($column)
    {
        $column = QueryBuilder::for(TableColumn::class)
            ->whereKey($column)
            ->apply()
            ->first();

        if (!$column) {
            throw (new ModelNotFoundException)->setModel(TableColumn::class);
        }

        return new Resource($column);
    }
}
