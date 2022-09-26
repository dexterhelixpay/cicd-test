<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use App\Models\PaymentStatus;
use Illuminate\Console\Command;
use App\Models\OrderNotification;
use App\Notifications\PaymentReminder;

class OrderRemind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:remind
        {--type= : Reminder type}
        {--orderIds=* : Remind only specific orders with the given IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind customers about their subscription';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        OrderNotification::query()
            ->where('is_enabled', true)
            ->where('notification_type', OrderNotification::NOTIFICATION_REMINDER)
            ->whereNotNull('days_from_billing_date')
            ->when($this->option('type') == 'after', function ($query) {
                $query->where('days_from_billing_date', '>', 0);
            })
            ->when($this->option('type') == 'before', function ($query) {
                $query->where('days_from_billing_date', '<', 0);
            })
            ->when($this->option('type') == 'today', function ($query) {
                $query->where('days_from_billing_date', 0);
            })
            ->cursor()
            ->tapEach(function (OrderNotification $notification) {
                $billingDate = now();

                if ($this->option('type') == 'after') {
                    $billingDate = now()->subDays($notification->days_from_billing_date);
                }

                if ($this->option('type') == 'before') {
                    $billingDate = now()->addDays(abs($notification->days_from_billing_date));
                }
                    $options = [];

                Order::query()
                    ->with([
                        'products',
                        'subscription.customer',
                        'subscription.merchant',
                    ])
                    ->has('products')
                    ->has('subscription.merchant')
                    ->whereHas('subscription', function ($query) use($notification) {
                        $query->where('merchant_id', $notification->merchant_id);
                    })
                    ->when($this->option('orderIds'), function ($query){
                        $query->whereIn(
                            'id',
                            $this->option('orderIds')
                        );
                    })
                    ->where(function ($query) {
                        $query
                            ->where('payment_type_id', '!=', PaymentType::CASH)
                            ->orWhere('payment_type_id', null);
                    })
                    ->whereIn('order_status_id', [
                        OrderStatus::UNPAID,
                        OrderStatus::INCOMPLETE,
                        OrderStatus::FAILED,
                    ])
                    ->whereDate('billing_date', $billingDate->toDateString())
                    ->whereNotNull('total_price')
                    ->cursor()
                    ->tapEach(function (Order $order) use ($options, $notification) {

                        if ($order->isNotNotifiable($notification)) {
                            return;
                        }

                        $options =  [
                            'title' => replace_placeholders($notification->headline, $order),
                            'subtitle' => replace_placeholders($notification->subheader, $order),
                            'subject' => replace_placeholders($notification->subject, $order),
                            'payment_headline' => replace_placeholders($notification->payment_headline, $order),
                            'payment_instructions' => replace_placeholders($notification->payment_instructions, $order),
                            'payment_button_label' => $notification->payment_button_label,
                            'total_amount_label' => $notification->total_amount_label,
                            'payment_instructions_headline' => replace_placeholders($notification->payment_instructions_headline, $order),
                            'payment_instructions_subheader' => replace_placeholders($notification->payment_instructions_subheader, $order),
                            'type' => $this->option('type'),
                            'has_pay_button' => (bool) $notification->payment_button_label,
                            'has_edit_button' => true,
                            'has_order_summary' => $order->isInitial()
                                && in_array($order->order_status_id, [
                                    OrderStatus::UNPAID,
                                    OrderStatus::INCOMPLETE,
                                    OrderStatus::FAILED,
                                ]),
                        ];

                        if (
                            $order->isInitial()
                            && $scheduleEmail = $order->subscription->scheduleEmail
                        ) {
                            $options['title'] =  replace_placeholders($scheduleEmail->headline, $order);
                            $options['subtitle'] =  replace_placeholders($scheduleEmail->subheader, $order);
                            $options['subject'] =  replace_placeholders($scheduleEmail->subject, $order);
                        }

                        $order->subscription->customer->notify(
                            new PaymentReminder(
                                $order->subscription,
                                $order->subscription->merchant,
                                $order->products->first(),
                                $order,
                                $options
                            )
                        );

                        $order->subscription->messageCustomer(
                            $order->subscription->customer,
                            'payment',
                            $order,
                            $this->option('type')
                        );
                    })
                    ->all();
            })
            ->all();

        return 0;
    }
}
