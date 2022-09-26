<?php

namespace App\Http\Controllers\Api\v1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\Customer;
use Illuminate\Http\Request;


class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Customer $customer)
    {
        $subscriptions = QueryBuilder::for($customer->subscriptions()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($subscriptions);
    }
}
