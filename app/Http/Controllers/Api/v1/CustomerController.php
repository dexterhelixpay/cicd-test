<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Order;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use App\Models\PaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use App\Models\OrderNotification;
use App\Services\CustomerService;
use App\Exports\CustomersTemplate;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MerchantFollowUpEmail;
use App\Notifications\PaymentReminder;
use App\Exceptions\BadRequestException;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('authorize')->only('downloadTemplate');
        $this->middleware('auth.client:user,merchant')->only('update', 'downloadTemplate');
        $this->middleware('auth.client:user,merchant,customer')->except('update', 'downloadTemplate');
        $this->middleware('permission:CP: Merchants - View|MC: Customers')->only('index', 'show');
        $this->middleware('permission:CP: Merchants - Edit|MC: Customers')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $customers = QueryBuilder::for(Customer::class)
            ->with('country')
            ->when($request->isFromCustomer(), function ($query, $user) {
                $query->whereKey($user->getKey());
            })
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($customers);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\CustomerService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, CustomerService $service)
    {
        if ($request->hasFile('data.attributes.file')) {
            $request->validate([
                'data.attributes.file' => 'required|file|mimes:xls,xlsx',
                'data.attributes.merchant_id' => [
                    Rule::requiredIf($request->isFromUser()),
                    Rule::exists('merchants', 'id')->withoutTrashed(),
                ],
            ]);

            if ($user = $request->isFromMerchant()) {
                $merchant = $user->merchant;
            } else {
                $merchant = Merchant::find($request->input('data.attributes.merchant_id'));
            }

            $customers = $service->createFromFile(
                $merchant, $request->file('data.attributes.file')
            );

            return new ResourceCollection($customers);
        }

        $country = Country::where('id', $request->input('data.attributes.country_id'))->first();
        $philippines = Country::where('name', 'Philippines')->first();

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.name' => 'required|string',
            'data.attributes.email' => 'required|email|max:191',
            'data.attributes.mobile_number' => [
                'required',
                Rule::when(
                    $country->id == $philippines->id,
                    ['mobile_number']
                )
            ],
            'data.attributes.country_id' => 'required|string'
        ]);

        return DB::transaction(function () use ($request) {
            $merchant = Merchant::findOrFail($request->userOrClient()->merchant_id);

            $customer = $merchant->customers()->create($request->input('data.attributes'));

            return new CreatedResource($customer->refresh());
        });
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $customer)
    {
        $country = Country::where('id', $request->input('data.attributes.country_id'))->first();
        $philippines = Country::where('name', 'Philippines')->first();

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.name' => 'sometimes|string',
            'data.attributes.email' => 'sometimes|email|max:191',
            'data.attributes.mobile_number' => [
                'required',
                Rule::when(
                    $country->id == $philippines->id,
                    ['mobile_number']
                )
            ],
            'data.attributes.country_id' => 'required|string'
        ]);

        $customer = Customer::findOrFail($customer);

        return DB::transaction(function () use ($request, $customer) {
            $customer->update($request->input('data.attributes', []));

            return new Resource($customer->fresh());
        });
    }

     /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $customer)
    {
        $customer = QueryBuilder::for(Customer::class)
            ->whereKey($customer)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->where('merchant_id', $request->userOrClient()->merchant_id);
            })
            ->apply()
            ->first();

        if (!$customer) {
            throw (new ModelNotFoundException)->setModel(Customer::class);
        }

        return new Resource($customer);
    }

     /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $customer)
    {
        $customer = Customer::find($customer);

        if (!optional($customer)->delete()) {
            throw (new ModelNotFoundException)->setModel(Merchant::class);
        }

        return response()->json([], 204);
    }

    /**
     * Download the template for customer bulk creation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadTemplate(Request $request)
    {
        if ($user = $request->isFromMerchant()) {
            $merchant = $user->merchant;
        } else {
            $merchant = null;
        }

        return (new CustomersTemplate($merchant))
            ->download('Import Customers Template.xlsx', Excel::XLSX);
    }

    /**
     * Send payment reminder to customer through email and mobile number.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function sendPaymentReminder(Request $request, Customer $customer)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.time' => 'required|string',
            'data.attributes.order_id' => [
                'required',
                Rule::exists('orders', 'id')
            ],
            'data.relationships.attachments.data' => 'nullable|array|email_attachment',
            'data.relationships.attachments.data.*.attributes.pdf' => [
                'mimes:pdf'
            ],
        ], [
            'data.relationships.attachments.data.*.attributes.pdf.mimes' => 'Attachment must be a file of type of pdf.'
        ]);

        if (!$customer->email && !$customer->mobile_number) {
            throw new BadRequestException("Selected customer neither have an email and mobile number.");
        }

        $order = Order::findOrFail($request->input('data.attributes.order_id'));

        $subscription = $order->subscription;
        $merchant = $subscription->merchant;
        $orderProduct  = $order->products->first();
        $orderNotificationId = data_get($request,'data.attributes.merchant_order_notification_id',null);

        $hasOrderSummary = $subscription->is_console_booking
            && are_all_single_recurrence($subscription->products)
            && $order->payment_status_id != PaymentStatus::PAID;

        switch ($request->input('data.attributes.time')) {
            case 'before':
                $options = $order->setReminderOptions(
                    'before',
                    $hasOrderSummary,
                    $orderNotificationId
                );

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $order,
                            $options
                        )
                    );
                }

                $subscription->messageCustomer($customer, 'payment', $order, 'before',$options);

                break;

            case 'today':
                $options = $order->setReminderOptions(
                    'today',
                    $hasOrderSummary,
                    $orderNotificationId
                );

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $order,
                            $options
                        ));
                }

                $subscription->messageCustomer($customer, 'payment', $order, 'today', $options);

                break;

            case 'after':
                $options = $order->setReminderOptions(
                    'after',
                    $hasOrderSummary,
                    $orderNotificationId
                );

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $order,
                            $options
                        ));
                }

                $subscription->messageCustomer($customer, 'payment', $order, 'after', $options);

                break;

            case 'edit':
                $title = "Edit your {$merchant->subscription_term_singular}";
                $subtitle = "Your changes will be automatically confirmed once update is clicked";

                $instructionHeadline = replace_placeholders(
                        $order->isSingle()
                            ? 'Payment is due on {billingDate}'
                            : 'Next Payment is due on {nextBillingDate}',
                        $order
                    );

                $instructionSubheader = replace_placeholders(
                        $order->isSingle()
                            ? 'Please pay on or before {billingDate}'
                            : 'You will be reminded for your next billing',
                        $order
                    );

                $options = [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'subject' => "Edit Your {$merchant->subscription_term_singular} #{$subscription->id}",
                    'payment_headline' =>'',
                    'payment_instructions' => '',
                    'payment_button_label' => '',
                    'total_amount_label' => 'Total Amount',
                    'payment_instructions_headline' => $instructionHeadline,
                    'payment_instructions_subheader' => $instructionSubheader,
                    'payment_instructions_subheader' => 'You will be reminded for your next billing',
                    'type' => 'edit',
                    'has_pay_button' => false,
                    'has_edit_button' => true,
                    'has_order_summary' => $order->isInitial(),
                ];

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $order,
                            $options
                        ));
                }

                $subscription->messageCustomer($customer, 'payment', $order, 'edit', $options);

                break;

            default:
                //
                break;
        }


        return $this->okResponse();
    }
}
