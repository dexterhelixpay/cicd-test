<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Exceptions\BadRequestException;
use App\Exceptions\SecureVoucherException;
use App\Exceptions\VoucherApplicationException;
use App\Exports\SecureVoucherTemplate;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Voucher;
use App\Models\UsedVoucher;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Services\VoucherService;
use App\Traits\ThrottlesSms;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Hash;
use Throwable;

class VoucherController extends Controller
{
    use ThrottlesSms;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,customer,null')->only('validateVoucher');

        $this->middleware('auth:user,merchant')
            ->only('index', 'store', 'show', 'update', 'destroy', 'bulkUpdate');

        $this->middleware('permission:CP: Merchants - Edit|MC: Vouchers')
            ->only('index', 'store', 'show', 'update', 'destroy', 'bulkUpdate');
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
        $vouchers = QueryBuilder::for($merchant->vouchers()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($vouchers);
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
            $voucher = $merchant->vouchers()->make($request->input('data.attributes'));
            $voucher->save();

            if ($request->hasFile('data.attributes.qualified_customers_file')) {
               (new VoucherService())->importQualifiedCustomer(
                   $voucher,
                   $request->file('data.attributes.qualified_customers_file')
                );
            }

            return new CreatedResource($voucher->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $voucher
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $voucher)
    {
        $voucher = QueryBuilder::for($merchant->vouchers()->getQuery())
            ->whereKey($voucher)
            ->apply()
            ->first();

        if (!$voucher) {
            throw (new ModelNotFoundException)->setModel(Voucher::class);
        }

        return new Resource($voucher);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $voucher
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $voucher)
    {
        $this->validateRequest(
            $request,
            $merchant,
            $voucher = $merchant->vouchers()->findOrFail($voucher)
        );

        return DB::transaction(function () use ($request, $voucher) {
            $voucher->update($request->input('data.attributes', []));

            if ($request->hasFile('data.attributes.qualified_customers_file')) {
                (new VoucherService())->importQualifiedCustomer(
                    $voucher,
                    $request->file('data.attributes.qualified_customers_file'),
                    true
                 );
             }

            return new Resource($voucher->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $voucher
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $voucher)
    {
        $voucher = $merchant->vouchers()->find($voucher);

        if (!optional($voucher)->delete()) {
            throw (new ModelNotFoundException)->setModel(Voucher::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the voucher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Validation\VoucherApplicationException
     */
    protected function bulkUpdate(Request $request, Merchant $merchant)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.voucher_code' => [
                'required',
                Rule::exists('vouchers', 'code')
                    ->where('merchant_id', $merchant->getKey())
                    ->whereNull('deleted_at')
            ],
            'data.attributes.targeted_order_ids' => 'required|array',
            'data.attributes.targeted_order_ids.*' => 'required',
        ], [
            'data.attributes.voucher_code.exists' => 'This is not an active voucher code',
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $voucher = $merchant->vouchers()->where('code', $request->input('data.attributes.voucher_code'))->first();

            if ($voucher->isExpired()) {
                throw new VoucherApplicationException(
                    'This is not an active voucher code.',
                    2
                );
            }

            $orders = $merchant->orders()
                ->whereNull('voucher_code')
                ->where('order_status_id', OrderStatus::UNPAID)
                ->whereKey($request->input('data.attributes.targeted_order_ids'))
                ->get();

            $unpaidOrderCount = $orders
                ->filter(function (Order $order) {
                    return $order->payment_status_id != PaymentStatus::PAID;
                })
                ->count();

            if ($unpaidOrderCount > $voucher->remaining_count) {
                throw new VoucherApplicationException(
                    'Not enough remaining voucher',
                    5
                );
            }

            $ordersInfo = [];

            $orders->each(function(Order $order) use($voucher, &$ordersInfo) {
                if (
                    $order->payment_status_id == PaymentStatus::PAID
                    || $order->order_status_id == OrderStatus::FAILED
                    || $order->order_status_id == OrderStatus::CANCELLED
                ) {
                    return;
                }

                try {
                    $voucher->use($order);
                    $order->setTotalPrice();

                    $subscription = $order->subscription;
                    $subscription->voucher_id = $voucher->id;
                    $subscription->saveQuietly();

                    if (!$order->total_price) {
                        $order = $order->fresh();
                        $order->forceFill(['payment_status_id' => PaymentStatus::PAID])->update();
                    }

                    array_push($ordersInfo, [
                        'order_id' => $order->id,
                        'status' => 'success',
                        'message' => 'Successfully applied voucher'
                    ]);
                } catch(Throwable $e) {
                    array_push($ordersInfo, [
                        'order_id' => $order->id,
                        'status' => 'failed',
                        'message' => $e->getMessage()
                    ]);
                }
            });

            return response()->json(['orders' => $ordersInfo]);
        });
    }

    /**
     * Download the template for secure vouchers
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadTemplate(Request $request)
    {
        return (new SecureVoucherTemplate())
            ->download('Secure Vouchers Template.xlsx', Excel::XLSX);
    }

    /**
     * Validate the voucher.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validateVoucher(Request $request)
    {
        $this->validateInfo($request);

        $customerId = data_get($request->input('data'), 'customer_id', null)
            ?? $request->userOrClient()?->id;

        $voucher = Voucher::validate(
            code: $request->input('data.code'),
            totalPrice: $request->input('data.total_price') ?? 0,
            merchantId: $request->input('data.merchant_id'),
            customerId: $customerId,
            products: data_get($request, 'data.products', [])
        );

        if ($voucher->is_secure_voucher) {
            if (!$customerId) {
                throw new VoucherApplicationException(
                    "This voucher is secured.",
                    12
                );
            }
        }


        return response()->json(['voucher' => $voucher]);
    }

    /**
     * Send OTP for the given secure voucher
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validateOtp(Request $request)
    {
        $this->validateInfo($request, 'validate_otp');

        return DB::transaction(function () use ($request) {
            $customer = Customer::findOrFail($request->input('data.customer_id'));
            $lastSubscription = $customer->subscriptions()->latest('id')->first();
            $voucher = Voucher::where('merchant_id', $request->input('data.merchant_id'))
                ->where('code', $request->input('data.voucher_code'))
                ->first();

            $isValid = Hash::check($request->input('data.code'), $customer->verification_code);

            if (!$isValid) {
                throw new BadRequestException(trans('auth.invalid_code'));
            }

            return response()->json([
                'voucher' => $voucher,
                'customer' => $customer->load('membership','membershipProducts','blastVouchers'),
                'lastSubscription' => $lastSubscription,
                'token' => $customer->createToken('Store Front Token')->accessToken
            ]);
        });
    }


    /**
     * Send OTP for the given secure voucher
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendOtp(Request $request)
    {
        $this->validateInfo($request, 'send_otp');

        return DB::transaction(function () use ($request) {
            $voucher = Voucher::where('code', $request->input('data.code'))->firstOrFail();
            $merchant = Merchant::findOrFail($request->input('data.merchant_id'));

            $qualifiedCustomer = $voucher->qualifiedCustomers()
                ->when($request->filled('data.mobile_number'), function ($query) use ($request) {
                    $query->where(function ($query) use($request) {
                        $query->whereJsonContains('mobile_numbers', "0{$request->input('data.mobile_number')}")
                            ->orWhereJsonContains('mobile_numbers', $request->input('data.mobile_number'));
                    });
                })
                ->when($request->filled('data.email'), function ($query) use ($request) {
                    $query->whereJsonContains('emails', $request->input('data.email'));
                })
                ->first();

            if (!$qualifiedCustomer) {
                throw new BadRequestException('Unverified Customer');
            }

            $customer = $merchant->customers()
                ->getQuery()
                ->when($request->filled('data.mobile_number'), function ($query) use ($request) {
                    $query->where('mobile_number', $request->input('data.mobile_number'));
                })
                ->when($request->filled('data.email'), function ($query) use ($request) {
                    $query->where('email', $request->input('data.email'));
                })->first();

            if (
                $customer
                && $qualifiedCustomer->customer_id
                && $customer->id != $qualifiedCustomer->customer_id
            ) {
                return Voucher::getErrorResponse(code: 16, throwError: true);
            }

            if (!$customer) {
                $customer = $merchant->customers()->create([
                    'merchant_id' => $request->input('data.merchant_id'),
                    'mobile_number' => data_get($request, 'data.mobile_number'),
                    'email' => data_get($request, 'data.email'),
                ]);
            }

            $this->throttleSms(function () use ($customer, $request) {
                $customer->sendVoucherVerificationCode(isMobileNumber: $request->filled('data.mobile_number'));
            });

            return response()->json([
                'customer' => $customer,
            ]);
        });
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $type
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateInfo(Request $request, $type = 'apply')
    {
        switch ($type) {
            case 'validate_otp':
                $request->validate([
                    'data.customer_id' => [
                        'required',
                        Rule::exists('customers', 'id')
                            ->whereNull('deleted_at'),
                    ],
                    'data.code' => 'required|string',
                    'data.voucher_code' => [
                        'required',
                        'string',
                        Rule::exists('vouchers', 'code')
                            ->whereNull('deleted_at')
                    ],
                    'data.merchant_id' => Rule::exists('merchants', 'id')
                        ->whereNull('deleted_at'),
                ]);
                break;

            case 'send_otp':
                $request->validate([
                    'data' => 'required',
                    'data.code' => [
                        'required',
                        'string',
                        Rule::exists('vouchers', 'code')
                            ->whereNull('deleted_at')
                    ],
                    'data.email' => 'required_if:data.attributes.mobile_number,null',
                    'data.mobile_number' => 'required_if:data.attributes.email,null',
                    'data.merchant_id' => Rule::exists('merchants', 'id')
                        ->whereNull('deleted_at'),
                ]);
                break;

            default:
                $request->validate([
                    'data' => 'required',
                    'data.code' => 'required',
                    'data.total_price' => 'required',
                    'merchant_id' => Rule::exists('merchants', 'id')
                        ->whereNull('deleted_at'),
                ]);
                break;
        }
    }
    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Voucher|null  $product
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $voucher = null)
    {
        $type = $request->input('data.attributes.type');
        $maxAmount = $type === Voucher::PERCENTAGE_TYPE ? '|max:100' : '';

        $totalCount = $request->input('data.attributes.total_count', 1);

        if ($voucher) {
            return $request->validate([
                'data' => 'required',
                'data.attributes' => 'required',

                'data.attributes.is_enabled' => 'sometimes|boolean',
                'data.attributes.code' => [
                    'sometimes',
                    Rule::unique('vouchers', 'code')
                        ->whereNull('deleted_at')
                        ->ignoreModel($voucher),
                ],
                'data.attributes.type' => ['sometimes', Rule::in(1, 2, 3)],
                'data.attributes.amount' => 'sometimes|numeric|min:0'. $maxAmount,

                'data.attributes.minimun_purchase_amount' => 'sometimes|numeric',

                'data.attributes.total_count' => 'sometimes|integer|min:1',
                'data.attributes.remaining_count' => 'sometimes|integer|min:0|max:'. $totalCount,
                'data.attributes.expires_at' => [
                    'sometimes',
                    'nullable',
                    'date_format:Y-m-d H:i:s',
                    'before:2038-01-19 03:14:07',
                ],
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.is_enabled' => 'required|boolean',
            'data.attributes.code' => [
                'required',
                Rule::unique('vouchers', 'code')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.type' => ['required', Rule::in(1, 2, 3)],
            'data.attributes.amount' => 'required|numeric|min:0'. $maxAmount,

            'data.attributes.minimun_purchase_amount' => 'sometimes|numeric',

            'data.attributes.total_count' => 'required|integer|min:1',
            'data.attributes.remaining_count' => 'required|integer|min:0|max:'. $totalCount,
            'data.attributes.expires_at' => [
                'nullable',
                'date_format:Y-m-d H:i:s',
                'before:2038-01-19 03:14:07',
            ],
        ]);
    }
}
