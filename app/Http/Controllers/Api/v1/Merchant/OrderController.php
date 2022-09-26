<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use Carbon\Carbon;
use App\Models\Bank;
use App\Models\Order;
use App\Models\Merchant;
use Illuminate\Support\Arr;
use App\Imports\OrderPrices;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Http\UploadedFile;
use App\Imports\OrderPricesImport;
use Illuminate\Support\Facades\DB;
use App\Exports\OrderSummaryExport;
use App\Http\Controllers\Controller;
use App\Exceptions\BadRequestException;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Validation\UnauthorizedException;
use App\Exports\OrderPrices as OrderPricesExport;
use App\Exports\OrderDetails as OrderDetailsExport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,customer');
        $this->middleware('permission:CP: Merchants - Edit|MC: Orders');
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
        $this->authorizeRequest($request, $merchant);
        $frequencies = data_get($request, 'filter.frequency', null);

        $orders = QueryBuilder::for($merchant->orders()->getQuery())
            ->when(data_get($request->query(), 'scope.upcomingTransactions'), function ($query) {
                return $query
                    ->upcomingTransactions();
            })
            ->where(function($query) use ($frequencies) {
                $query->when($frequencies, function ($query) use ($frequencies){
                    foreach (explode(',',$frequencies) as $frequency) {
                        $query->orWhereJsonContains('orders.payment_schedule->frequency', $frequency);
                    }
                });
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($orders);
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
        if ($request->hasFile('data.order_prices')) {
            return $this->importOrderPrices($request);
        }

        if ($request->hasFile('data.attributes.price_file')) {
            return $this->updateOrderPrices($request, $merchant);
        }

        if ($request->hasFile('data.*.relationships.attachments.data.*.attributes.file')) {
            return $this->attachFiles($request, $merchant);
        }

        return $this->okResponse();
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $order)
    {
        $order = QueryBuilder::for($merchant->orders()->getQuery())
            ->whereKey($order)
            ->apply()
            ->first();

        if (!$order) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return new Resource($order);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $order)
    {
        $order = $merchant->orders()->findOrFail($order);

        $this->authorizeRequest($request, $merchant);

        if (
            ($request->hasFile('data.relationships.attachments.data.*.attributes.pdf')
            || $request->has('data.relationships.attachments.data'))
            && $request->isNotFilled('data.attributes')
        ) {
            return $this->syncAttachments($request, $order);
        }

        if (
            $request->isNotFilled('data.attributes')
            && $request->filled('data.relationships.products.data')
        ) {
            return DB::transaction(function () use ($request, $order) {
                $order->syncOrderedProducts(
                    $request->input('data.relationships.products.data', [])
                );

                if ($request->allFiles()) {
                    $order->subscription->saveShopifyImages($request->allFiles(), $order);
                }

                return new Resource($order->fresh('products'));
            });
        }

        $this->validateRequest(
            $request,
            $merchant,
            $order
        );

        return DB::transaction(function () use ($request, $order) {
            $bankCode = data_get($request, 'data.attributes.bank_code', null);
            $order->fill($request->input('data.attributes'))
                ->forceFill([
                    'bank_id' => $bankCode
                        ? Bank::where('code',$bankCode)->first()->id
                        : $order->bank_id ?? null
                ])
                ->update();

            $subscription = $order->subscription()->first();

            if (
                !($request->hasOnly('order_status_id', 'data.attributes')
                || $request->hasOnly(['order_status_id', 'payment_type_id'], 'data.attributes'))
                && !$request->isFromMerchant()
            ) {
                $subscription->notifyCustomer('edit-confirmation');
            }

            return new Resource($order->fresh('attachments'));
        });
    }

        /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function syncAttachments(Request $request, $order)
    {
        return DB::transaction(function () use ($request, $order) {
            if (
                $request->hasFile('data.relationships.attachments.data.*.attributes.pdf')
                || $request->has('data.relationships.attachments.data')
            ) {
                $attachmentIds = collect($request->input('data.relationships.attachments.data'))->pluck('id');

                $subscription = $order->subscription()->first();

                collect($request->file('data.relationships.attachments.data'))
                    ->pluck('attributes.pdf')
                    ->each(function ($pdf) use ($subscription, &$attachmentIds) {
                        $attachment = $subscription->attachments()->make();
                        $attachment->size = $pdf->getSize();
                        $attachment->name = $pdf->getClientOriginalName();

                        $attachment->uploadAttachment($pdf)->save();

                        $attachmentIds->push($attachment->id);
                    });

                $order->attachments()->sync($attachmentIds);
            }

            return new Resource($order->fresh('attachments'));
        });
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

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Order|null  $order
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $order = null)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.relationships.attachments.data' => 'nullable|array|email_attachment',
            'data.relationships.attachments.data.*.attributes.pdf' => 'sometimes',
        ], [
            'data.relationships.attachments.data.*.attributes.pdf.mimes' => 'Attachment must be a file of type of pdf.'
        ]);
    }

    /**
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function attachFiles($request, $merchant)
    {
        $request->validate([
            'data' => 'required',
            'data.*.id' => [
                'required',
                function ($attribute, $value, $fail) use ($merchant) {
                    $exists = Order::query()
                        ->whereKey($value)
                        ->whereHas('subscription', function ($query) use ($merchant) {
                            $query->where('merchant_id', $merchant->getKey());
                        })
                        ->exists();

                    if (!$exists) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],

            'data.*.relationships.attachments.data.*.attributes.file' => [
                'required',
                'mimes:jpg,png,pdf',
            ],
        ]);

        DB::transaction(function () use ($request) {
            $orders = collect($request->all()['data'])
                ->map(function ($data) {
                    $order = Order::find($data['id'])->load('subscription');

                    $attachments = collect(data_get($data, 'relationships.attachments.data'))
                        ->pluck('attributes.file')
                        ->map(function (UploadedFile $file) use ($order) {
                            $attachment = $order->subscription->attachments()
                                ->make()
                                ->uploadFile($file);

                            $attachment->save();

                            return $attachment->fresh();
                        });

                    $order->attachments()->syncWithoutDetaching(
                        $attachments->pluck('id')->toArray()
                    );

                    return $order->fresh('attachments');
                });

            return new ResourceCollection($orders);
        });
    }

    /**
     * Import order prices from the specified file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function importOrderPrices(Request $request)
    {
        $request->validate([
           'data.order_prices'  => 'required|mimes:csv,xlsx'
        ]);

        (new OrderPricesImport)->import($request->file('data.order_prices'));

        return $this->okResponse();
    }

    /**
     * Export the given orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function exportOrderSummary(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        if (!$request->filled('columns')) {
            throw new BadRequestException(
                'Columns are required.'
            );
        }

        $columns = collect(data_get($request, 'columns'))->map(function($column) {
            return Arr::only($column, ['text','value']);
        });

        if (filter_var($request->input('filter.template'), FILTER_VALIDATE_BOOL)) {
            $query = Order::whereNull('id');
            $fileName = 'Orders Template.xlsx';
        } else {
            $query = QueryBuilder::for($merchant->orders()->getQuery())
                ->apply()
                ->with(['paymentType','subscription.customer', 'products.product']);
            $fileName = "{$merchant->name} Orders (" . now()->format('YmdHis') . ').xlsx';
        }

        return (new OrderSummaryExport($query, $columns))
            ->download($fileName);
    }


    /**
     * Export the given orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function export(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        if (filter_var($request->input('filter.template'), FILTER_VALIDATE_BOOL)) {
            $query = Order::whereNull('id');
            $fileName = 'Orders Template.xlsx';
        } else {
            $query = QueryBuilder::for($merchant->orders()->getQuery())
                ->apply()
                ->with('products.product', 'subscription.customer');

            $fileName = "{$merchant->name} Orders (" . now()->format('YmdHis') . ').xlsx';
        }

        return (new OrderPricesExport($query))->download($fileName);
    }

    /**
     * Export the given orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function exportOrderDetails(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        if (filter_var($request->input('filter.template'), FILTER_VALIDATE_BOOL)) {
            $query = Order::whereNull('id');
            $fileName = 'Orders Template.xlsx';
        } else {
            $query = QueryBuilder::for($merchant->orders()->getQuery())
                ->apply()
                ->with(['paymentType','subscription.customer', 'products.product']);
            $fileName = "{$merchant->name} Orders (" . now()->format('YmdHis') . ').xlsx';
        }

        $customFields = $merchant->subscriptionCustomFields()->where('is_visible', true)->get();

        return (new OrderDetailsExport($query, $customFields))
            ->download($fileName);
    }


    /**
     * Update the given orders' prices.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateOrderPrices(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        $request->validate([
            'data.attributes.price_file' => 'required|mimes:xlsx',
        ]);

        $orderPrices = new OrderPrices($merchant);
        $orderPrices->import($request->file('data.attributes.price_file'));

        return new ResourceCollection($orderPrices->getOrders(), Order::class);
    }

    /**
     * Bulk send of payment reminder to customer through email and mobile number.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkSendPaymentReminder(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.time' => 'required|string',
            'data.attributes.targeted_order_ids' => 'required|array',
            'data.attributes.targeted_order_ids.*' => 'required',
        ]);

        collect(data_get($request, 'data.attributes.targeted_order_ids'))
            ->map(function($orderId) {
                return Order::findOrFail($orderId) ?: false;
            })
            ->each(function($order) use ($request) {
                $order->sendPaymentReminder(data_get($request,'data.attributes.time'));
            });

        return $this->okResponse();

    }

    /**
     * Bulk order update of fulfillment and shipping date.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdateShippedOrFulfilled(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.date' => 'nullable|string',
            'data.attributes.targeted_order_ids' => 'required|array',
            'data.attributes.targeted_order_ids.*' => 'required',
        ]);

        return DB::transaction(function () use ($request) {

            $orders = collect(data_get($request, 'data.attributes.targeted_order_ids'))
                ->map(function($orderId) {
                    return Order::findOrFail($orderId) ?: false;
                })
                ->each(function($order) use ($request) {
                    $date = data_get($request, 'data.attributes.date');

                    if ($order->hasShippableProducts()) {
                        if ($order->shipped_at && $date) return;
                        $order->update([
                            'shipped_at' => $date
                        ]);
                    }
                    if ($order->hasDigitalProducts()) {
                        if ($order->fulfilled_at && $date) return;
                        $order->update([
                            'fulfilled_at' => $date
                        ]);
                    }
                });

            return new ResourceCollection($orders);

        });
    }
}
