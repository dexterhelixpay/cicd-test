<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use App\Support\PaymentSchedule;
use Illuminate\Support\Facades\DB;

class OrderCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:create
        {--orderIds=* : Remind only specific orders with the given IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the next order of the overdues';

    /**
     * Constant representing a default days before overdue.
     *
     * @var int
     */
    const DAYS_BEFORE_OVERDUE = 5;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Order::query()
            ->with('subscription.merchant')
            ->whereHas('subscription.merchant', function ($query) {
                $query->where('is_outstanding_balance_enabled', true);
            })
            ->whereDate('billing_date', '<=', now()->toDateString())
            ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::FAILED, OrderStatus::INCOMPLETE])
            ->where(function ($query) {
                $query
                    ->whereNull('payment_schedule')
                    ->orWhere('payment_schedule->frequency', '<>', 'single');
            })
            ->when($this->option('orderIds'), function ($query){
                $query->whereIn(
                    'id',
                    $this->option('orderIds')
                );
            })
            ->cursor()
            ->tapEach(function (Order $order) {
                DB::transaction(function () use ($order) {
                    $recurrence =  $order->subscription
                        ->merchant
                        ->recurrences()
                        ->where('code', $order->payment_schedule
                            ? $order->payment_schedule['frequency']
                            : null
                        )
                        ->first();

                    if (
                        $recurrence
                        && now()->diffInDays($order->billing_date) >= ($recurrence->days_before_overdue ?: self::DAYS_BEFORE_OVERDUE)
                        && (!$order->nextOrder
                        || $order->isInitial())
                    ) {
                        if (!$order->isInitial()) {
                            $order->subscription->generateNextOrders(
                                $order->group_number,
                                null,
                                $order
                            );
                        }

                        $nextOrder = !$order->isInitial()
                            ? $order->fresh()->nextOrder
                            : $order->subscription->nextOrder;

                        if (!$nextOrder) return;

                        $order->update(['order_status_id' => OrderStatus::OVERDUE]);
                        $this->syncOverdueOrders($nextOrder, $order);
                    }
                });
            })
            ->all();

        return 0;
    }

    public function syncOverdueOrders($nextOrder, $order) {
        $totalPrice = $nextOrder->total_price + $order->total_price;

        $nextOrder->update([
            'total_price'=> $totalPrice,
            'previous_balance'=> $order->total_price ?? 0
        ]);

        $nextOrder->overdueOrders()->sync(
            Arr::prepend($order->overdueOrders->pluck('id')->toArray(),$order->id)
        );
        $order->overdueOrders()->sync([]);
    }
}
