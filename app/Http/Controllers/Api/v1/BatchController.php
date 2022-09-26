<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,null');
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param string $batchId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, string $batchId)
    {
        $batch = ImportBatch::find($batchId);

        if (!$batch) {
            abort(404);
        }

        return $this->okResponse(['batch' => $batch])
         ->header('Content-Type', 'application/vnd.api+json');
    }
}
