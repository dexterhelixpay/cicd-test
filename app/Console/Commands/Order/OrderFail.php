<?php

namespace App\Console\Commands\Order;

use App\Models\Order;
use App\Facades\Xendit;
use App\Libraries\Xendit\EWalletCharge;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use App\Models\PaymentStatus;
use App\Services\ProductService;
use Illuminate\Console\Command;

class OrderFail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:fail
        {--lapsed : Fail only orders with lapsed payments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tag orders as failed based on the given criteria';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('lapsed')) {
            return $this->failLapsedPayments();
        }

        return 0;
    }

    /**
     * Tag orders with lapsed payments as failed.
     *
     * @return void
     */
    public function failLapsedPayments()
    {
        Order::query()
            ->with('subscription.merchant')
            ->has('products')
            ->has('subscription.customer')
            ->has('subscription.merchant')
            ->lapsed()
            ->cursor()
            ->tapEach(function (Order $order) {
                $this->refreshPaymentStatus($order);

                if ($order->refresh()->order_status_id !== OrderStatus::PAID) {
                    $order
                        ->paymentStatus()
                        ->associate(PaymentStatus::PENDING)
                        ->syncOriginalAttribute('payment_status_id')
                        ->paymentStatus()
                        ->associate(PaymentStatus::INCOMPLETE)
                        ->forceFill([
                            'has_payment_lapsed' => true,
                            'ignores_inventory' => false,
                        ])
                        ->save();

                    (new ProductService)->restoreStocks(
                        $order->subscription->merchant,
                        $order->products->toArray()
                    );
                }
            })
            ->all();
    }

    /**
     * Refresh payment status of the given order.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function refreshPaymentStatus(Order $order)
    {
        switch ((int) $order->payment_type_id) {
            case PaymentType::GCASH:
            case PaymentType::GRABPAY:
                $chargeId = data_get($order, 'payment_info.charge.id');
                $xenditAccountId = $order->subscription?->merchant?->xenditAccount?->xendit_account_id;

                if ($chargeId && $xenditAccountId) {
                    $response = Xendit::eWalletCharges()->find($chargeId, $xenditAccountId);

                    if ($response->successful()) {
                        $order->payment_info = ['charge' => $response->json()];
                    }

                    if (data_get($order, 'payment_info.charge.status') === EWalletCharge::STATUS_SUCCESS) {
                        $order->payment_status_id = PaymentStatus::PAID;
                    }

                    $order->save();
                }

                break;

            default:
                //
        }
    }
}
