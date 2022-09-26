<?php

namespace App\Console\Commands;

use App\Models\MerchantFollowUpEmail;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Notifications\PaymentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class OrderFollowUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:follow-up
        {--orderIds=* : Remind only specific orders with the given IDs}
        {--type= : Reminder type}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Follow up customer about their order';

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
        MerchantFollowUpEmail::query()
            ->where('is_enabled', true)
            ->when($this->option('type') == 'after', function ($query) {
                $query->where('days', '>', 0);
            })
            ->when($this->option('type') == 'before', function ($query) {
                $query->where('days', '<', 0);
            })
            ->when($this->option('type') == 'today', function ($query) {
                $query->where('days', 0);
            })
            ->cursor()
            ->tapEach(function (MerchantFollowUpEmail $merchantFollowUpEmail) {
                $billingDate = now();

                if ($this->option('type') == 'after') {
                    $billingDate = now()->subDays($merchantFollowUpEmail->days);
                }

                if ($this->option('type') == 'before') {
                    $billingDate = now()->addDays(abs($merchantFollowUpEmail->days));
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
                    ->whereHas('subscription', function ($query) use($merchantFollowUpEmail) {
                        $query->where('merchant_id', $merchantFollowUpEmail->merchant_id);
                    })
                    ->when($merchantFollowUpEmail->recurrences, function ($query) use ($merchantFollowUpEmail){
                        $query->whereIn(
                            'payment_schedule->frequency',
                            collect($merchantFollowUpEmail->recurrences)->values()
                        );
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
                    ->tapEach(function (Order $order) use ($billingDate, $options, $merchantFollowUpEmail) {
                        $initialOrder = $order->subscription->initialOrder()->first();
                        $isInitialOrder = $initialOrder->is($order);

                        $hasPaidOrder = $order->subscription->orders()
                            ->where('order_status_id', OrderStatus::PAID)
                            ->count();

                        if ($isInitialOrder && $order->order_status_id == OrderStatus::FAILED) {
                            return;
                        }

                        if (!$isInitialOrder && !$hasPaidOrder) {
                            return;
                        }

                        $options = array_merge($options, [
                            'title' => replace_placeholders($merchantFollowUpEmail->headline, $order),
                            'subtitle' => replace_placeholders($merchantFollowUpEmail->body, $order),
                            'next_payment_subtitle' => "Your payment was due on {$billingDate->format('F j')}",
                            'subject' => replace_placeholders($merchantFollowUpEmail->subject, $order)
                        ]);

                        if (count($order->attachments) > 0) {
                            $options['subtitle'] .= ', See attached file for full invoice.';
                        }

                        $options['has_order_summary'] = $order->subscription->is_console_booking
                            && are_all_single_recurrence($order->subscription->products)
                            && $order->payment_status_id != PaymentStatus::PAID;

                        if ($options['has_order_summary']) {
                            $options['has_pay_button'] = false;
                        }

                        $options['type'] = $merchantFollowUpEmail->days < 0
                            ? 'before'
                            : 'after';

                        if ($merchantFollowUpEmail->days == 0) {
                            $options['type'] = 'today';
                        }

                        if (
                            $isInitialOrder
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
                            'after'
                        );
                    })
                    ->all();
            })
            ->all();

        return 0;
    }
}
