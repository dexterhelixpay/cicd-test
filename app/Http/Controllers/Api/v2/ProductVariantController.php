<?php

namespace App\Http\Controllers\Api\v2;

use App\Exports\ProductVariants as ProductVariantExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckStocksRequest;
use App\Http\Requests\ProductVariant\BulkUpdateProductVariantRequest;
use App\Http\Requests\ProductVariant\ImportProductVariantRequest;
use App\Http\Requests\ProductVariant\ManageProductVariantRequest;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Imports\ProductVariants as ProductVariantImport;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\v2\ProductVariant;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('logged')->only('bulkUpdate', 'update');
        $this->middleware('authorize')->only('export');
        $this->middleware('auth:api,user,merchant');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $variants = QueryBuilder::for(ProductVariant::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->whereHas('product', function ($query) use ($user) {
                    $query->where('merchant_id', $user->merchant_id);
                });
            })
            ->apply()
            ->fetch(true);

        return new ResourceCollection($variants);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \App\Http\Requests\BulkUpdateProductVariantRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request)
    {
        $variants = QueryBuilder::for(ProductVariant::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->whereHas('product', function ($query) use ($user) {
                    $query->where('merchant_id', $user->merchant_id);
                });
            })
            ->apply()
            ->fetch();

        if ($variants instanceof LengthAwarePaginator) {
            $variants = $variants->getCollection();
        }

        return (new ProductVariantExport($variants))
            ->download('Inventory (' . now()->format('YmdHis') . ').xlsx');
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \App\Http\Requests\ImportProductVariantRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(ImportProductVariantRequest $request)
    {
        (new ProductVariantImport($request->merchant))->import($request->file('file'));

        return response()->json([], 204);
    }

    /**
     * Check the stocks of the requested products.
     *
     * @param  \App\Http\Requests\CheckStocksRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStocks(CheckStocksRequest $request, ProductService $service)
    {
        $service->checkStocks($request->merchant, $request->validated());

        return response()->json([], 200);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \App\Http\Requests\BulkUpdateProductVariantRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(BulkUpdateProductVariantRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $variants = collect($request->validated())
                ->map(function ($data) {
                    $variant = ProductVariant::find($data['id']);

                    if ($addedStock = data_get($data, 'added_stock')) {
                        $variant->stock = is_null($variant->stock)
                            ? null
                            : $variant->stock + $addedStock;
                    } elseif (Arr::has($data, 'stock')) {
                        $variant->stock = data_get($data, 'stock');
                    }

                    return tap($variant)
                        ->update(Arr::except($data, ['id', 'stock']));
                });

            return new ResourceCollection($variants);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $productVariant
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $productVariant)
    {
        $variant = QueryBuilder::for(ProductVariant::class)
            ->whereKey($productVariant)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->whereHas('product', function ($query) use ($user) {
                    $query->where('merchant_id', $user->merchant_id);
                });
            })
            ->apply()
            ->first();

        if (!$variant) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class);
        }

        return new Resource($variant);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ManageProductVariantRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(
        ManageProductVariantRequest $request,
        ProductVariant $productVariant
    ) {
        return DB::transaction(function () use ($request, $productVariant) {
            $productVariant->update($request->validated());

            return new Resource($productVariant);
        });
    }
}
