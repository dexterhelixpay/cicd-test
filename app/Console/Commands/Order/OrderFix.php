<?php

namespace App\Console\Commands\Order;

use App\Models\Order;
use App\Models\OrderedProduct;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use App\Models\SubscribedProduct;
use App\Support\PaymentSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:fix
        {id : The order ID}
        {--detect : Detect potential problems}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix common problems with orders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$order = Order::find($this->argument('id'))) {
            return $this->getOutput()->error('The selected order is invalid.');
        }

        if ($this->option('detect')) {
            return $this->detectIssues($order);
        }

        if (!$order->payment_schedule) {
            return $this->fixPaymentSchedule($order);
        }

        return 0;
    }

    /**
     * Detect the correct payment schedule for the given product.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function detectIssues(Order $order)
    {
        $order->loadMissing([
            'products',
            'subscription.customer.cards',
            'subscription.customer.wallets',
            'subscription.merchant',
        ]);

        $warnings = [];
        $issues = [];

        if (
            !$order->is_auto_charge
            && in_array($order->payment_type_id, [PaymentType::CARD, PaymentType::PAYMAYA_WALLET])
        ) {
            $warning = 'Order is set to auto remind. Subscription is set to auto '
                . ($order->subscription->is_auto_charge ? 'charge.' : 'remind.');

            $warnings[] = $warning;
        }

        if ($order->products->isEmpty()) {
            $issues[] = "Order doesn't have any products.";
        }

        if (!$order->subscription?->merchant) {
            $issues[] = "Merchant doesn't exist.";
        }

        if (!$order->subscription?->customer) {
            $issues[] = "Customer doesn't exist.";
        }

        if (!$order->payment_schedule && !$order->isInitial()) {
            $issues[] = 'Payment schedule is missing.';
        }

        if (!in_array($order->order_status_id, [OrderStatus::UNPAID, OrderStatus::FAILED])) {
            $issues[] = 'Order status must be unpaid/failed for auto charging.';
        }

        if (
            $order->payment_type_id == PaymentType::CARD
            && !$order->subscription->paymaya_card_token_id
        ) {
            $issue = 'No card is binded to the subscription.';

            $hasVerifiedCards = $order->subscription
                ?->customer
                ?->cards
                ?->filter(fn ($card) => $card->isVerified())
                ?->isNotEmpty();

            $issue .= $hasVerifiedCards
                ? ' Customer has vaulted cards.'
                : ' Customer has no vaulted cards.';

            $issues[] = $issue;
        }

        if (
            $order->payment_type_id == PaymentType::PAYMAYA_WALLET
            && !$order->subscription->paymaya_link_id
        ) {
            $issue = 'No wallet is binded to the subscription.';

            $hasVerifiedWallets = $order->subscription
                ?->customer
                ?->wallets
                ?->filter(fn ($card) => $card->isVerified())
                ?->isNotEmpty();

            $issue .= $hasVerifiedWallets
                ? ' Customer has vaulted wallets.'
                : ' Customer has no vaulted wallets.';

            $issues[] = $issue;
        }

        if (count($warnings)) {
            $this->table(['Warnings'], collect($warnings)->map(fn ($w) => [$w]));
        }

        if (count($issues)) {
            $this->table(['Issues'], collect($issues)->map(fn ($i) => [$i]));
        }

        if (!count($warnings) && !count($issues)) {
            $this->getOutput()->success('No problems detected.');
        }
    }

    /**
     * Detect the correct payment schedule for the given product.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function fixPaymentSchedule(Order $order)
    {
        $order->load(['products', 'subscription.products', 'subscription.orders']);

        $this->getOutput()->warning('Payment schedule is missing.');

        if ($order->isInitial()) {
            if (!$this->confirm('This is the first order of the subscription. Continue?')) {
                return;
            }
        }

        $hasDifferentSchedules = $order->products
            ->map(function (OrderedProduct $product) {
                return PaymentSchedule::toKeyString($product->payment_schedule);
            })
            ->unique()
            ->count() > 1;

        if ($hasDifferentSchedules) {
            $this->getOutput()->warning('Ordered products have different schedules.');

            $this->comment('Subscribed Products');
            $this->table(
                ['ID', 'Product ID', 'Variant ID', 'Title', 'Payment Schedule', 'Total Price'],
                $order->subscription->products->map(function (SubscribedProduct $product) {
                    return [
                        $product->getKey(),
                        $product->product_id ?? 'N/A',
                        $product->product_variant_id ?? 'N/A',
                        $product->title,
                        $product->payment_schedule
                            ? PaymentSchedule::toKeyString($product->payment_schedule)
                            : 'N/A',
                        $product->total_price,
                    ];
                })
            );

            $this->newLine();

            $this->comment('Ordered Products');
            $this->table(
                ['Sub Product ID', 'Product ID', 'Variant ID', 'Title', 'Payment Schedule', 'Total Price'],
                $order->products->map(function (OrderedProduct $product) {
                    return [
                        $product->subscribed_product_id,
                        $product->product_id ?? 'N/A',
                        $product->product_variant_id ?? 'N/A',
                        $product->title,
                        $product->payment_schedule
                            ? PaymentSchedule::toKeyString($product->payment_schedule)
                            : 'N/A',
                        $product->total_price,
                    ];
                })
            );

            $this->newLine();

            $this->comment('Orders under Subscription');
            $this->table(
                ['Order ID', 'Order Status', 'Billing Date', 'Payment Schedule', 'Total Price'],
                $order->subscription->orders->map(function (Order $order) {
                    return [
                        $order->getKey(),
                        $order->order_status_id,
                        $order->billing_date->toDateString(),
                        $order->payment_schedule
                            ? PaymentSchedule::toKeyString($order->payment_schedule)
                            : 'N/A',
                        $order->total_price ?: 'Free',
                    ];
                })
            );

            $paymentSchedule['frequency'] = $this->choice('Select frequency', [
                'weekly',
                'semimontly',
                'monthly',
                'bimonthly',
                'quarterly',
                'semiannual',
                'annually',
            ]);

            switch ($paymentSchedule['frequency']) {
                case 'monthly':
                case 'bimonthly':
                case 'quarterly':
                case 'semiannual':
                case 'annually':
                    $paymentSchedule['day'] = $this->ask('Select day');
                    break;

                case 'weekly':
                case 'semimonthly':
                    $paymentSchedule['day_of_week'] = $this->ask('Select day of week');
                    break;
            }

            $this->info(json_encode($paymentSchedule, JSON_PRETTY_PRINT));

            if (!$this->confirm('Do you want to set this as the new payment schedule?')) {
                return;
            }

            DB::transaction(function () use ($order, $paymentSchedule) {
                $order->updateQuietly(['payment_schedule' => $paymentSchedule]);
                $order->products()->update(['payment_schedule' => $paymentSchedule]);

                $order->subscription->products()
                    ->where(function ($query) use ($order) {
                        $productIds = $order->products->pluck('product_id')->filter()->values();

                        if ($productIds->isNotEmpty()) {
                            $query->orWhereIn('product_id', $productIds);
                        }

                        $subscribedProductIds = $order->products
                            ->pluck('subscribed_product_id')
                            ->filter()
                            ->values();

                        if ($subscribedProductIds->isNotEmpty()) {
                            $query->orWhereIn('subscribed_products.id', $subscribedProductIds);
                        }
                    })
                    ->update(['payment_schedule' => $paymentSchedule]);
            });

            $this->getOutput()->success('Payment schedule successfully updated');

            if (!$this->confirm('Would you like to update the billing date of the order?')) {
                return;
            }

            $date = null;

            while (
                !Validator::make(compact('date'), ['date' => 'required|date_format:Y-m-d'])->passes()
            ) {
                $date = $this->ask('Enter billing date');
            }

            $order->updateQuietly(['billing_date' => $date]);

            $this->getOutput()->success('Billing date successfully updated');
        }
    }
}
