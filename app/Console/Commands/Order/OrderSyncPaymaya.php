<?php

namespace App\Console\Commands\Order;

use App\Facades\v2\PayMaya;
use App\Libraries\PayMaya\v2\Payment;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use Illuminate\Console\Command;

class OrderSyncPaymaya extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:sync-paymaya
        {id : The order ID}
        {--payment-id= : The PayMaya payment ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the payment info of the given order with PayMaya';

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
        /** @var \App\Models\Order */
        $order = Order::query()
            ->with('subscription.merchant')
            ->whereHas('subscription.merchant', function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('orders.payment_type_id', PaymentType::CARD)
                        ->whereNotNull('merchants.paymaya_vault_secret_key');
                })->orWhere(function ($query) {
                    $query
                        ->where('orders.payment_type_id', PaymentType::PAYMAYA_WALLET)
                        ->whereNotNull('merchants.paymaya_pwp_secret_key');
                });
            })
            ->find($this->argument('id'));

        if (!$order) {
            return $this->getOutput()->error('Order not found');
        }

        if (!$paymentId = $this->option('payment-id') ?? data_get($order, 'payment_info.payment.id')) {
            return $this->getOutput()->error('PayMaya payment ID not found');
        }

        $secretKey = intval($order->payment_type_id) === PaymentType::CARD
            ? $order->subscription->merchant->paymaya_vault_secret_key
            : $order->subscription->merchant->paymaya_pwp_secret_key;

        $response = PayMaya::payments()->find($paymentId, $secretKey);

        if ($response->failed()) {
            return $this->getOutput()->error($response->reason());
        }

        $this->table(['Key', 'Value'], [
            ['Order ID', $order->getKey()],
            ['Order Status', $order->status->name],
            ['Payment Status', $order->paymentStatus->name],
            ['PayMaya Payment ID', $response->json('id')],
            ['PayMaya Payment Status', $response->json('status')],
        ]);

        if (!$this->confirm('Do you want to update the order status?')) {
            return Command::SUCCESS;
        }

        switch ($response->json('status')) {
            case Payment::SUCCESS:
                $order->paymentStatus()->associate(PaymentStatus::PAID);
                break;

            case Payment::FAILED:
            case Payment::EXPIRED:
                $order->paymentStatus()->associate(PaymentStatus::FAILED);
                break;

            default:
                return $this->getOutput()->success('PayMaya payment status is not supported.');
        }

        $order->payment_info = ['payment' => $response->json()];
        $order->save();

        $this->getOutput()->success('Order status updated successfully.');

        return Command::SUCCESS;
    }
}
