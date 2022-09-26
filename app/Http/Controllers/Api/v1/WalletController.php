<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\UnauthorizedException;

class WalletController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $customer)
    {
        $deobfuscatedCustomer = decodeId($customer, 'customer');

        if (empty($deobfuscatedCustomer[0])) {
            throw new UnauthorizedException;
        }

        $customer = Arr::first(Customer::find($deobfuscatedCustomer));

        if (!$customer) {
            throw new UnauthorizedException;
        }

        $wallets = QueryBuilder::for($customer->wallets()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($wallets);
    }
}
