<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Exports\CustomerSummaryExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\UnauthorizedException;

class CustomerController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,customer')->only('update', 'destroy');
        $this->middleware('auth:user,merchant,customer,null')->only('index', 'show', 'store');
        $this->middleware('auth:user,merchant')->only('export');
        $this->middleware('permission:CP: Merchants - Edit|MC: Customers')->only('update', 'destroy');
        $this->middleware('permission:MC: Customers')->only('export');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $customers = QueryBuilder::for($merchant->customers()->getQuery())
            ->when($request->has('filter.isBlast'), function ($query) use ($request, $merchant) {
                $uniqueCustomers = $merchant->customers()
                    ->select('id', 'email', 'mobile_number', 'country_id', 'created_at')
                    ->withCount('subscriptions')
                    ->where('is_unsubscribed', false)
                    ->get()
                    ->groupBy(function (Customer $customer) {
                        return join('.', [
                            $customer->email,
                            $customer->mobile_number,
                            $customer->country_id,
                        ]);
                    })
                    ->map(function (Collection $customers) {
                        $subGroups = $customers->groupBy('subscriptions_count');
                        $maxSubCount = $subGroups->keys()->sortDesc()->first();

                        return $subGroups
                            ->get($maxSubCount)
                            ->sortByDesc('created_at')
                            ->first();
                    })
                    ->values();

                $query
                    ->whereKey($uniqueCustomers->pluck('id'))
                    ->where('is_unsubscribed', false)
                    ->when($request->input('filter.cancelled_at'), function ($query, $cancelledAt){
                        $query->whereHas('subscriptions', function ($query) use ($cancelledAt) {
                            $cancelledAt === 'null'
                                ? $query->whereNull('cancelled_at')
                                : $query->whereNotNull('cancelled_at');
                        });
                    })
                    ->when($request->input('filter.hasNoSubscription'), function ($query) {
                        $query->doesntHave('subscriptions');
                    })
                    ->when($request->input('filter.customPaymentStatus'), function ($query) use($request) {
                        if ($request->input('filter.customPaymentStatus') == 'paid') {
                            $query->whereHas('orders', function ($query) {
                                $query->whereDate('paid_at', '<=', now()->toDateString())
                                    ->where('payment_status_id', PaymentStatus::PAID)
                                    ->where('orders.payment_schedule->frequency', '<>', 'single')
                                    ->whereNotIn('order_status_id', [
                                        OrderStatus::CANCELLED,
                                        OrderStatus::FAILED,
                                        OrderStatus::OVERDUE,
                                        OrderStatus::INCOMPLETE
                                    ]);
                            })
                            ->whereDoesntHave('orders', function ($query) {
                                $query->where('billing_date', '<=', now()->toDateString())
                                    ->whereIn('payment_status_id', [
                                        PaymentStatus::NOT_INITIALIZED,
                                        PaymentStatus::PENDING,
                                        PaymentStatus::INCOMPLETE
                                    ]);
                            });
                        } else {
                            $query->whereDoesntHave('orders', function ($query) {
                                $query->where('payment_status_id', PaymentStatus::PAID);
                            });
                        }
                    })
                    ->when($request->input('filter.paymentDateFrom') || $request->input('filter.paymentDateTo'), function ($query) use ($request) {
                            $paymentDateFrom = $request->input('filter.paymentDateFrom');
                            $paymentDateTo = $request->input('filter.paymentDateTo');

                            $query->whereHas('orders', function($query) use ($paymentDateFrom, $paymentDateTo) {
                                $query->whereBetween('paid_at', [
                                        $paymentDateFrom,
                                        Carbon::parse($paymentDateTo)->endOfDay() ?? now()->endOfDay()
                                    ])
                                    ->where('payment_status_id', PaymentStatus::PAID);
                            });
                    })
                    ->when($request->input('filter.consecutiveOrdersCount'), function ($query, $consecutiveOrderCount) {
                        $query->whereHas('subscriptions', function ($query) use ($consecutiveOrderCount) {
                            $query->whereHas('orders', function($query) {
                                $query->where('payment_status_id', PaymentStatus::PAID);
                            }, '>=', $consecutiveOrderCount);
                        });
                    })
                    ->when($request->input('filter.daysWithoutPayment'), function ($query, $daysWithoutPayment) {
                        $query->whereHas('orders', function ($query) use ($daysWithoutPayment){
                            $query
                                ->whereDate('billing_date', '<=', today())
                                ->where(function($query) use ($daysWithoutPayment) {
                                    $query
                                        ->whereDate('paid_at', '<=', today()->subDays($daysWithoutPayment))
                                        ->orWhereNull('paid_at');
                                });
                        });
                    })
                    ->when($request->input('filter.totalPaidOrders'), function ($query, $totalPaidOrders) {
                        $query
                            ->withSum(['orders' => function ($query) {
                                $query->where('payment_status_id', PaymentStatus::PAID);
                            }], 'total_price')
                            ->having('orders_sum_total_price', '>=' , $totalPaidOrders);
                    });
            })
            ->apply()
            ->fetch();

        return new ResourceCollection(
            $customers,
            Customer::class
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Merchant $merchant)
    {
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $customer = $merchant->customers()->create($request->input('data.attributes'));

            return new CreatedResource($customer->fresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $customer)
    {
        $customer = QueryBuilder::for($merchant->customers()->getQuery())
            ->whereKey($customer)
            ->apply()
            ->first();

        if (!$customer) {
            throw (new ModelNotFoundException)->setModel(Customer::class);
        }

        return new Resource($customer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $customer)
    {
        $this->validateRequest(
            $request,
            $merchant,
            $customer = $merchant->customers()->findOrFail($customer)
        );

        return DB::transaction(function () use ($request, $customer) {
            $customer->update($request->input('data.attributes'));

            return new Resource($customer->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $customer)
    {
        $customer = $merchant->customers()->find($customer);

        if (!optional($customer)->delete()) {
            throw (new ModelNotFoundException)->setModel(Customer::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Customer|null  $customer
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $customer = null)
    {
        if ($customer) {
            return $request->validate([

            ]);
        }

        $country = Country::where('id', $request->input('data.attributes.country_id'))->first();
        $philippines = Country::where('name', 'Philippines')->first();

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.name' => 'required|string',
            'data.attributes.email' => [
                'required',
                'email',
                Rule::unique('customers', 'email')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.mobile_number' => [
                'required',
                Rule::when(
                    $country->id == $philippines->id,
                    ['mobile_number']
                ),
                Rule::unique('customers', 'mobile_number')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.country_id' => [
                'required',
                Rule::unique('countries', 'email')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.address' => 'required|string',
            'data.attributes.province' => 'required|string',
            'data.attributes.city' => 'required|string',
            'data.attributes.barangay' => 'required|string',
            'data.attributes.zip_code' => 'required|string|max:5',
        ]);
    }

    /**
     * Export the given customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function export(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

            $query = QueryBuilder::for($merchant->customers()->getQuery())
                ->apply()
                ->with('merchant.customFields');
            $fileName = "{$merchant->name} Customers (" . now()->format('YmdHis') . ').xlsx';

        return (new CustomerSummaryExport($query))->download($fileName);
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $merchant)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }

}
