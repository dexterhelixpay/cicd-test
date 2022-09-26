<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderPay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:pay
        {--date= : Charge orders on the given billing date}
        {--id=* : Charge only the given order IDs}
        {--retry : Retry failed payments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge subscriptions using card/wallet';

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
        if ($this->option('retry')) {
            return $this->retryPayments();
        }

        $this->triggerPayments(
            $this->option('date') ?: now()->toDateString(),
            $this->option('id')
        );

        return 0;
    }

    /**
     * Retry failed payments.
     *
     * @return int
     */
    protected function retryPayments()
    {
        Order::query()
            ->has('products')
            ->has('subscription.customer')
            ->has('subscription.merchant')
            ->whereNotNull('payment_schedule')
            ->where('is_auto_charge', true)
            ->whereIn('payment_type_id', [PaymentType::CARD, PaymentType::PAYMAYA_WALLET])
            ->where('order_status_id', OrderStatus::FAILED)
            ->where('auto_payment_attempts', '<', 3)
            ->whereNull('payment_initiator_id')
            ->where('billing_date', now()->toDateString())
            ->where('payment_attempted_at', '<=', now()->subHour()->toDateTimeString())
            ->cursor()
            ->tapEach(function (Order $order) {
                try {
                    $order->startPayment();
                } catch (Throwable $e) {
                    Log::error($e->getMessage(), [
                        'order' => $order->getKey(),
                        'file' => __FILE__,
                        'line' => __LINE__,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            })
            ->all();

        return 0;
    }

    /**
     * Trigger payments.
     *
     * @param  string  $billingDate
     * @param  array  $orderIds
     * @return int
     */
    protected function triggerPayments(string $billingDate, array $orderIds = [])
    {
        $orders = Order::query()
            ->has('products')
            ->has('subscription.customer')
            ->has('subscription.merchant')
            ->whereNotNull('payment_schedule')
            ->where('is_auto_charge', true)
            ->where(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query
                        ->where('payment_type_id', PaymentType::CARD)
                        ->whereHas('subscription', function ($query) {
                            $query->whereNotNull('paymaya_card_token_id');
                        });
                })->orWhere(function (Builder $query) {
                    $query
                        ->where('payment_type_id', PaymentType::PAYMAYA_WALLET)
                        ->whereHas('subscription', function ($query) {
                            $query->whereNotNull('paymaya_link_id');
                        });
                });
            })
            ->payable()
            ->orderBy('orders.id')
            ->when(count($orderIds), function (Builder $query) use ($orderIds) {
                $query->whereKey($orderIds);
            }, function (Builder $query) use ($billingDate) {
                $query->whereDate('billing_date', $billingDate);
            })
            ->when($this->option('no-interaction'), function (Builder $query) {
                $minutesFromStart = now()->startOfMinute()->diffInMinutes(
                    Carbon::parse(setting('AutoChargeStartTime', '07:00'))
                );

                $page = ($minutesFromStart / 10) + 1;

                return $query->forPage($page, 100);
            })
            ->cursor()
            ->map(function (Order $order) {
                try {
                    $order->startPayment();
                } catch (Throwable $e) {
                    Log::channel($this->option('no-interaction') ? 'stack' : 'single')
                        ->error($e->getMessage(), [
                            'order' => $order->getKey(),
                            'file' => __FILE__,
                            'line' => __LINE__,
                            'trace' => $e->getTraceAsString(),
                        ]);

                    return [
                        $order->getKey(),
                        $order->payment_type_id,
                        'NO',
                        $order->order_status_id,
                        $e->getCode() ?? 'N/A',
                        $e->getMessage() ?? 'N/A',
                    ];
                }

                return $order->fresh();
            })
            ->all();

        if ($this->option('no-interaction') || count($orders) === 0) {
            return 0;
        }

        $this->table(
            ['Order ID', 'Payment Type ID', 'Processed?', 'Order Status', 'Error Code', 'Error Message'],
            collect($orders)->map(function ($order) {
                if (is_array($order)) {
                    return $order;
                }

                return [
                    $order->getKey(),
                    $order->payment_type_id,
                    'YES',
                    $order->order_status_id,
                    data_get(
                        $order,
                        'payment_info.error.body.code',
                        data_get($order, 'payment_info.payment.errorCode', 'N/A')
                    ),
                    data_get(
                        $order,
                        'payment_info.error.body.message',
                        data_get($order, 'payment_info.payment.errorMessage', 'N/A')
                    ),
                ];
            })->toArray()
        );

        return 0;
    }
}
