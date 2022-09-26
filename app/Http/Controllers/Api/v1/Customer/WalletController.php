<?php

namespace App\Http\Controllers\Api\v1\Customer;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;

class WalletController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,customer,null');
        $this->middleware('permission:CP: Merchants - Edit|MC: Customers');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Customer $customer)
    {
        $this->authorizeRequest($request, $customer);

        $customers = QueryBuilder::for($customer->wallets()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($customers);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @param  \App\Models\CustomerWallet  $wallet
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Customer $customer, CustomerWallet $wallet)
    {
        $this->authorizeRequest($request, $customer);

        return DB::transaction(function () use ($wallet, $customer) {
            // $isUsed = Subscription::query()
            //     ->whereNull('completed_at')
            //     ->whereNull('cancelled_at')
            //     ->where(function ($query) use ($wallet) {
            //         $query
            //             ->where('paymaya_link_id', $wallet->link_id)
            //             ->orWhereHas('orders', function ($query) use ($wallet) {
            //                 $query->where('paymaya_link_id', $wallet->link_id);
            //             });
            //     })
            //     ->exists();

            // if ($isUsed) {
            //     throw new BadRequestException("The wallet is still being used on active {$customer->merchant->subscription_term_plural}.");
            // }

            if (!$wallet->delete()) {
                throw (new ModelNotFoundException)->setModel(CustomerWallet::class);
            }

            return response()->json([], 204);
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $customer)
    {
        $user = $request->userOrClient();

        if ($request->isFromMerchant() && $customer->merchant_id !== $user->merchant_id) {
            throw new UnauthorizedException;
        }

        if ($request->isFromCustomer() && $customer->getKey() !== $user->getKey()) {
            throw new UnauthorizedException;
        }
    }
}
