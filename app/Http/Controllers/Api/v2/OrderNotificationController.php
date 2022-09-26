<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrderNotificationRequest;
use App\Http\Requests\ManageOrderNotificationRequest;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\OrderNotification;
use App\Services\OrderNotificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderNotificationController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
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
        $notifications = QueryBuilder::for(OrderNotification::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($notifications);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreateOrderNotificationRequest  $request
     * @param  \App\Services\OrderNotificationService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(
        CreateOrderNotificationRequest $request, OrderNotificationService $service
    ) {
        return DB::transaction(function () use ($request, $service) {
            $notifications = $service->create(
                merchant: $request->merchant,
                notificationType: OrderNotification::NOTIFICATION_REMINDER,
                purchaseType: $request->validated('purchase_type'),
                subscriptionType: $request->validated('subscription_type'),
                applicableOrders: $request->validated('applicable_orders'),
                daysFromBillingDate: $request->validated('days_from_billing_date'),
                recurrences: $request->validated('recurrences'),
                copies: $request->safe()->only([
                    'subject',
                    'headline',
                    'subheader',

                    'payment_headline',
                    'payment_instructions',
                    'payment_button_label',

                    'total_amount_label',

                    'payment_instructions_headline',
                    'payment_instructions_subheader',
                ])
            );

            if ($notifications->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_type' => 'No valid notifications were created.',
                ]);
            }

            return new ResourceCollection($notifications);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OrderNotification  $orderNotification
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(
        ManageOrderNotificationRequest $request, OrderNotification $orderNotification
    ) {
        return new Resource($orderNotification);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ManageOrderNotificationRequest  $request
     * @param  \App\Models\OrderNotification  $orderNotification
     * @param  \App\Services\OrderNotificationService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(
        ManageOrderNotificationRequest $request,
        OrderNotification $orderNotification,
        OrderNotificationService $service
    ) {
        return DB::transaction(function () use ($request, $orderNotification, $service) {
            $service
                ->fillNotificationDefaults($orderNotification->fill($request->validated()))
                ->save();

            return new Resource($orderNotification);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Http\Requests\ManageOrderNotificationRequest  $request
     * @param  \App\Models\OrderNotification  $orderNotification
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(
        ManageOrderNotificationRequest $request, OrderNotification $orderNotification
    ) {
        if (!optional($orderNotification)->delete()) {
            throw (new ModelNotFoundException)->setModel(OrderNotification::class);
        }

        return response()->json([], 204);
    }
}
