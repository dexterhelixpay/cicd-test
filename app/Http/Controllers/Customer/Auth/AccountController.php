<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:customer');
    }

    /**
     * Get the current user's info.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInfo(Request $request)
    {
        $customer = $request->user();

        $user = QueryBuilder::for(Customer::class)
            ->whereKey($customer->getKey())
            ->with([
                'membership',
                'membershipProducts',
                'country',
                'blastVouchers',
            ])
            ->apply()
            ->first();

        $memberships = (new CustomerService)->getActiveSubscriptions(
            $customer, function ($query) {
                $query->whereRelation('products', 'is_membership', true);
            }
        );

        return response()->json($user->setRelation('memberships', $memberships));
    }
}
