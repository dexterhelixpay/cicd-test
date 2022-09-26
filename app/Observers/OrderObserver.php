<?php

namespace App\Observers;

use App\Events\XenditPaymentPaid;
use App\Facades\Discord;
use App\Jobs\CalculateTotalPaidTransactions;
use App\Jobs\CreateShopifyOrder;
use App\Jobs\WebhookEvents\OrderCancelled;
use App\Jobs\WebhookEvents\OrderShipped;
use App\Jobs\WebhookEvents\OrderSkipped;
use App\Jobs\WebhookEvents\PaymentFailed;
use App\Jobs\WebhookEvents\PaymentSuccess;
use App\Libraries\PayMaya\v2\Payment;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderStatus;
use App\Models\OrderedProduct;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Models\SubscribedProduct;
use App\Models\Voucher;
use App\Notifications\PaymentReminder;
use App\Notifications\WelcomeEmailNotification;
use App\Services\ProductService;
use Illuminate\Support\Carbon;
use App\Models\Product;

class OrderObserver
{
    /**
     * Handle the order "creating" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function creating($order)
    {
        $this->unsetAutoChargeFlag($order);
    }

    /**
     * Handle the order "updating" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updating($order)
    {
        $this->updateOrderStatus($order);
        $this->clearPaymentUrl($order);
        $this->unsetAutoChargeFlag($order);
    }

     /**
     * Handle the order "updating" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated($order)
    {
        $this->setShippingDate($order);
        $this->setFulfillmentDate($order);
        $this->broadcastXenditPaymentStatus($order);
        $this->updateProductSales($order);
        $this->sendNotificationToCustomer($order);
        $this->updatePaymentStatus($order);
        $this->postUpdatesToWebhooks($order);
        $this->notifyCustomerAboutOrderPriceUpdate($order);
        $this->updateTotalAmountPaid($order);
        $this->updateUnpaidOrderPaymentType($order);
        $this->completeSubscription($order);
        $this->createAttemptLog($order);
        $this->cascadePaymentType($order);
        $this->cascadePaymentSchedule($order);
        $this->calculateTotalPaidTransactions($order);
        $this->clearPaymentDate($order);
    }

    /**
     * Handle the order "synced" event.
     *
     * @param  \App\Models\Order  $order
     * @param  string  $relation
     * @param  array  $properties
     * @return void
     */
    public function synced($order, $relation, $properties)
    {
        if ($relation === 'attachments') {
            $subscription = $order->subscription()->first();

            $attachmentIds = $subscription->attachments()->get()->pluck('id')->toArray();

            $currentAttachmentIds = OrderAttachment::whereIn('attachment_id', $attachmentIds)->get()->pluck('attachment_id')->toArray();

            Attachment::whereIn('id', array_diff($attachmentIds, $currentAttachmentIds))->get()->each->delete();
        }
    }

    /**
     * Calculate the merchant's total paid transactions.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function calculateTotalPaidTransactions($order)
    {
        if ($order->wasChanged('order_status_id', 'total_price')) {
            dispatch(new CalculateTotalPaidTransactions($order->subscription->merchant_id))
                ->afterResponse();
        }
    }

    /**
     * Broadcast xendit payment status
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function broadcastXenditPaymentStatus($order)
    {
        if (
            $order->wasChanged('order_status_id')
            && $order->order_status_id == OrderStatus::PAID
            && in_array($order->payment_type_id, [PaymentType::GCASH, PaymentType::GRABPAY])
        ) {
            XenditPaymentPaid::dispatch($order->id);
        }
    }

    /**
     * Cascade payment type to subscription
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function cascadePaymentType($order)
    {
        if (!$order->wasChanged('payment_type_id')) return;

        $subscription = $order->subscription;

        $cleanUpBankDetails = $order->payment_type_id != PaymentType::BANK_TRANSFER
            && $order->bank_id;

        if ($cleanUpBankDetails) {
            $order->forceFill(['bank_id' => null])->saveQuietly();
        }

        $subscription->forceFill([
            'payment_type_id' => $order->payment_type_id,
            'bank_id' => $cleanUpBankDetails ? null : $subscription->bank_id
        ])->saveQuietly();
    }

    /**
     * Cascade payment schedule to subscribed and ordered product
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function cascadePaymentSchedule($order)
    {
        if (!$order->wasChanged('payment_schedule') || !$order->payment_schedule) {
            return;
        }

        $subscription = $order->subscription;

        $order->products
            ->each(function(OrderedProduct $product) use ($order) {
                $product->update(['payment_schedule' => $order->payment_schedule]);
            });

        $subscription->products()
            ->where('group_number', $order->group_number)
            ->each(function(SubscribedProduct $product) use ($order) {
                $product->update(['payment_schedule' => $order->payment_schedule]);
            });
    }

    /**
     * Complete the subscription once fully paid.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function completeSubscription($order)
    {
        $subscription = $order->subscription()->first();

        if (
            $order->wasChanged('order_status_id')
            && $subscription->hasPaidAllOrders()
        ) {
            $subscription->completed_at = now();
            $subscription->save();
        }
    }

    /**
     * Post to merchant webhooks about order updates.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function updateProductSales($order)
    {
        if ($order->wasChanged('order_status_id')) {
            $subscription = $order->subscription()->first();
            $merchant = $subscription->merchant()->first();

            $service = new ProductService;

            $initialOrder = $subscription->initialOrder()->first();

            if ($initialOrder->id != $order->id) return;

            switch ((int) $order->order_status_id) {
                case OrderStatus::PAID:
                    $service->incrementSales(
                        $merchant,
                        $subscription->products()->get()->toArray()
                    );

                    break;

                case OrderStatus::UNPAID:
                    $service->decrementSales(
                        $merchant,
                        $subscription->products()->get()->toArray()
                    );

                    break;

                default:
                    //
            }
        }
    }

    /**
     * Post to merchant webhooks about order updates.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function postUpdatesToWebhooks($order)
    {
        if ($order->wasChanged('order_status_id')) {
            switch ((int) $order->order_status_id) {
                case OrderStatus::PAID:
                    $event = new PaymentSuccess($order);
                    break;

                case OrderStatus::FAILED:
                    $event = new PaymentFailed($order);
                    break;

                case OrderStatus::SKIPPED:
                    $event = new OrderSkipped($order);
                    break;

                case OrderStatus::CANCELLED:
                    $event = new OrderCancelled($order);
                    break;

                default:
                    //
            }
        }


        if ($order->wasChanged('shipped_at') && is_null($order->getOriginal('shipped_at'))) {
            $event = new OrderShipped($order);
        }

        if (isset($event)) {
            dispatch($event->postTo($order->subscription->merchant_id))->afterCommit();
        }
    }

    /**
     * Unset the auto-charge flag if the payment type is not auto chargeable.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function unsetAutoChargeFlag($order)
    {
        $isAutoChargeable = in_array((int) $order->payment_type_id, [
            PaymentType::CARD,
            PaymentType::PAYMAYA_WALLET,
        ]);

        if ($order->isDirty('payment_type_id') && !$isAutoChargeable) {
            $order->is_auto_charge = false;
        }
    }

     /**
     * Update the fulfilment date
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function setFulfillmentDate($order)
    {
        if (!$order->wasChanged('fulfilled_at')) return;

        $order->fill([
            'fulfillment_date' => $order->fulfilled_at ? now()->toDateString() : null
        ])
        ->saveQuietly();
    }

    /**
     * Update the shipping date
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function setShippingDate($order)
    {
        if (!$order->wasChanged('shipped_at')) return;

        $order->fill([
            'shipping_date' => $order->shipped_at ? now()->toDateString() : null
        ])
        ->saveQuietly();

        $order->subscription->notifyCustomer('shipped');
    }

    /**
     * Update the status of the given order.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function sendNotificationToCustomer($order)
    {
        if (!$order->wasChanged('order_status_id')) {
            return;
        }

        $subscription = $order->subscription()->first();
        $merchant = $subscription->merchant()->first();

        switch ((int) $order->order_status_id) {
            case OrderStatus::PAID:
                if ($order->subscription->is_shopify_booking) {
                    dispatch(new CreateShopifyOrder($order))->afterResponse();

                    if ($order->previous_balance) {
                        $order->overdueOrders()->get()->each(function (Order $overdueOrder) {
                            dispatch(new CreateShopifyOrder($overdueOrder))->afterResponse();
                        });
                    }
                }

                $initialOrder = $subscription->initialOrder()->first();

                if ($initialOrder->is($order) && $order->voucher){
                    $subscription
                        ->orders
                        ->whereNull('voucher_code')
                        ->each(function($nextOrder) use ($order, $subscription) {

                            $usedVoucherCount = $subscription->customer
                                ->usedVouchers()
                                ->where('id', $subscription->voucher_id)
                                ->count();

                            $usageCount = $subscription->voucher->applicable_order_count ?? PHP_INT_MAX;

                            if ($subscription->voucher->remaining_count && $usedVoucherCount < $usageCount) {
                                $subscription->voucher->use($nextOrder, throwError: false);
                                $nextOrder->setTotalPrice();
                            }
                        });
                }

                $this->sumMerchantTotalHourlyPaid($merchant, $order->total_price);

                $isBillingDateLateForAMonth = Carbon::parse($order->billing_date)
                    ->startOfDay()
                    ->diffInDays(now(), false)
                    >= 30;

                if ($isBillingDateLateForAMonth) {
                    $order->forceFill([
                            'billing_date' => now()->toDateString()
                        ])
                        ->saveQuietly();

                    $subscription->forceFill([
                            'cancelled_at' => null
                        ])
                        ->saveQuietly();
                }

                if (data_get($order->payment_schedule, 'frequency') != 'single') {
                    $isManuallyPaid = $order->wasChanged('payment_type_id')
                        && $order->payment_type_id == PaymentType::CASH;

                     $originalPaymentType = $order->getOriginal('payment_type_id');

                    if ($isManuallyPaid) {
                        $order->setTotalPrice();
                    }

                    $subscription->generateNextOrders(
                        groupNumber: $order->group_number,
                        action: $isManuallyPaid ? 'manually_paid' : null,
                        order: $order,
                        originalPaymentType: $originalPaymentType
                    );
                }

                $isFirstPayment = $subscription->orders()->where('order_status_id', OrderStatus::PAID)->count() < 2;

                $subscription->notifyCustomer(
                    $isFirstPayment
                    && $subscription->max_payment_count !== 1
                        ? 'confirmed' : 'success', false, $order
                );

                $this->sendWelcomeEmail($order);

                break;

            case OrderStatus::INCOMPLETE:
            case OrderStatus::FAILED:
                $subscription->notifyCustomer('failed', false, $order);
                break;

            case OrderStatus::SKIPPED:
                if ($voucher = $order->voucher){
                    $order->voucher->increment('remaining_count');
                    $order->forceFill(['voucher_code' => null])->saveQuietly();
                    $order->voucher()->detach();
                    $voucher->restoreCustomerSlot($subscription);
                }

                $subscription->generateNextOrders(
                    $order->group_number,
                    'skipped',
                    $order
                );

                $subscription->notifyCustomer('skipped', false, $order);

                break;

            // case OrderStatus::CANCELLED:
            //     $subscription->notifyCustomer('cancelled', false, $order);
            //     break;
        }
    }

    /**
     * Sum total hourly paid of merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  $amount
     *
     * @return void
     */
    protected function sumMerchantTotalHourlyPaid($merchant, $amount)
    {
        $merchant->hourly_total_amount_paid += $amount;
        $merchant->update();
    }

    /**
     * Update the status of the given order.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function clearPaymentUrl($order)
    {
        if (!$order->isDirty('order_status_id')) {
            return;
        }

        if (
            $order->total_price
            && in_array($order->order_status_id, [OrderStatus::PAID, OrderStatus::FAILED])
        ) {
            $order->payment_url = null;
        }
    }

    /**
     * Update the status of the given order.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function updateOrderStatus($order)
    {
        if (!$order->isDirty('payment_status_id')) {
            return;
        }

        $merchant = $order->subscription->merchant;

        switch ((int) $order->payment_status_id) {
            case PaymentStatus::PAID:
                $order->order_status_id = OrderStatus::PAID;
                $order->paid_at = now()->toDateTimeString();

                if ($order->isInitial() ) {
                    $order->subscription->sendNewSubscriberNotification();
                }

                if (
                    $order->isDirty('payment_type_id')
                    && $order->getRawOriginal('payment_type_id') === PaymentType::CARD
                ) {

                    $order->total_price = $order->original_price ?: $order->total_price;

                    if ($voucher = $order->voucher()->first()) {
                        if ($order->wasChanged('shipping_method_id')) {
                            $shippingFee = optional($order->shippingMethod)->price;
                        } else {
                            $shippingFee = $order->shipping_fee
                                ?: optional($order->shippingMethod)->price
                                ?: 0;
                        }

                        if (!are_shippable_products($order->products->pluck('product'))) {
                            $shippingFee = 0;
                        }

                        $subtotal = ($order->products()->sum('total_price') ?: 0) + $shippingFee;

                        $discount = $voucher->computeTotalDiscount([
                            'totalPrice' => $subtotal,
                            'products' => $order->products()->get(),
                            'customer' => $order->subscription->customer,
                            'order' => $order
                        ]);

                        $discount = $voucher->computeTotalDiscount([
                            'totalPrice' => $subtotal,
                            'products' => $order->products()->get(),
                            'customer' => $order->subscription->customer,
                            'order' => $order
                        ]);

                        $order->total_price -= data_get($discount, 'discount_amount');
                    }
                }

                if ($import = $order->subscription->subscriptionImport) {
                    $purchaseCount = (int) $import->purchase_count + 1;
                    $purchasePercent =  ($purchaseCount / $import->subscription_count) * 100;

                    $import->fill([
                        'purchased_amount' => $order->isInitial()
                            ? $import->purchased_amount += $order->total_price
                            : $import->purchased_amount,
                        'purchase_count' => $order->isInitial()
                            ? $purchaseCount
                            : $import->purchase_count,
                        'purchase_percentage' => $order->isInitial()
                            ? $purchasePercent
                            : $import->purchase_percentage,
                        'ltv_amount' => $import->ltv_amount += $order->total_price
                    ])
                    ->saveQuietly();
                }

                if ($merchant->discord_guild_id) {
                    $order->products
                        ->each(function(OrderedProduct $orderedProduct) use ($order, $merchant) {
                            if (
                                $orderedProduct->subscribedProduct?->is_active_discord_member
                                || !$order->subscription->discord_user_id
                                || !$orderedProduct->product->discord_channel
                            ) {
                                return;
                            }

                            Discord::guilds()
                                ->addUserRole(
                                    $merchant->discord_guild_id,
                                    $order->subscription->discord_user_id,
                                    $orderedProduct->product->discord_role_id
                                );

                            $orderedProduct->subscribedProduct
                                ->fill([
                                    'is_active_discord_member' => true
                                ])
                                ->saveQuietly();
                        });
                }

                if ($order->hasShippableProducts() && $merchant->shipping_days_after_payment) {
                    $order->shipping_date = now()
                        ->addDays($merchant->shipping_days_after_payment);
                }

                if ($order->hasDigitalProducts() && $merchant->fulfillment_days_after_payment) {
                    $order->fulfillment_date = now()
                        ->addDays($merchant->fulfillment_days_after_payment);
                }
                break;

            case PaymentStatus::INCOMPLETE:
                $order->order_status_id = OrderStatus::INCOMPLETE;
                break;

            case PaymentStatus::FAILED:
                $order->order_status_id = OrderStatus::FAILED;
                break;

            default:
                //
        }
    }

    /**
     * Create payment date if order status is unpaid
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function clearPaymentDate($order)
    {
        if (!$order->wasChanged('order_status_id')) return;

        if ($order->order_status_id == OrderStatus::UNPAID) {
            $order->paid_at = null;
            $order->saveQuietly();
        }
    }

    /**
     * Create a log of the transaction's state.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function createAttemptLog($order)
    {
        if (!$order->wasChanged('payment_attempts')) return;

        $log = $order->attemptLogs()->make($order->toArray());

        $log->save();
    }

    /**
     * Notify the customer when the merchant set the price after the billing date.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function notifyCustomerAboutOrderPriceUpdate($order)
    {
        if (
            !request()->isFromMerchant()
            || !$order->wasChanged('total_price')
            || !$order->isPayable()
        ) {
            return;
        }

        $dayDiff = now()->startOfDay()->diffInDays($order->billing_date, false);

        $hasOrderSummary = $order->isInitial()
            && in_array($order->order_status_id, [
                OrderStatus::UNPAID,
                OrderStatus::FAILED,
            ]);

        if ($dayDiff === 0) {
            $options = $order->setReminderOptions(
                'today',
                $hasOrderSummary,
            );

            $when = 'today';
        }
        if ($dayDiff > 0 && $dayDiff <= 3) {
            $options = $order->setReminderOptions(
                'before',
                $hasOrderSummary,
            );

            $when = 'before';
        }

        if ($dayDiff < 0 && $dayDiff >= -3 && $order->isRemindable()) {
            $options = $order->setReminderOptions(
                'after',
                $hasOrderSummary,
            );

            $when = 'after';
        }

        if (isset($options) && isset($when)) {
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
                $when
            );
        }
    }

    /**
     * Update the payment status of the given order.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function updatePaymentStatus($order)
    {
        if (
            $order->order_status_id == OrderStatus::PAID
            && request()->isFromMerchant()
            && $order->wasChanged('order_status_id')
        ) {
            $order->fill([
                'payment_status_id' => PaymentStatus::PAID,
                'paid_at' => now()->toDateTimeString(),
            ])
            ->saveQuietly();
        }
    }

    /**
     * Update the subscription's lifetime value.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function updateTotalAmountPaid($order)
    {
        $isOrWasPaid = $order->getOriginal('order_status_id') == OrderStatus::PAID
            || $order->order_status_id == OrderStatus::PAID;

        if ($order->wasChanged('order_status_id') && $isOrWasPaid) {
            $order->subscription->forceFill([
                'total_amount_paid' => $order->subscription->orders()
                    ->where('order_status_id', OrderStatus::PAID)
                    ->whereNotNull('total_price')
                    ->where('total_price', '>', 0)
                    ->sum('total_price'),
            ])->saveQuietly();
        }
    }

    /**
     * Update the unpaid order's payment type.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function updateUnpaidOrderPaymentType($order)
    {
        if (
            request()->isFromMerchant()
            && $order->isDirty('payment_type_id')
            && $order->wasChanged('payment_type_id')
        ) {
            $subscription = $order->subscription;
            $subscription->orders()
                ->whereNull('paid_at')
                ->get()
                ->each(function ($order) {
                    $order->fill([
                        'payment_type_id' => $order->payment_type_id
                    ])->saveQuietly();
                });
        }
    }

    /**
     * Send product welcome emails to customer once paid.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function sendWelcomeEmail($order)
    {
        if (!$order->isPaid()) {
            return;
        }

        if (!$subscription = $order->subscription()->with('merchant')->first()) {
            return;
        }

        if (!$customer = $subscription->customer()->first()) {
            return;
        }

        $subscription->products()
            ->with('product.welcomeEmail')
            ->has('product.welcomeEmail')
            ->get()
            ->each(function (SubscribedProduct $product) use ($order, $subscription, $customer) {
                $hasSentWelcomeEmail = $customer->subscriptions()
                    ->whereHas('products', function ($query) use ($product) {
                        $query->where('product_id', $product->product_id);
                    })
                    ->whereHas('orders', function ($query) use ($order) {
                        $query
                            ->whereKeyNot($order->getKey())
                            ->where('order_status_id', OrderStatus::PAID);
                    })
                    ->exists();

                if (!$hasSentWelcomeEmail) {
                    $customer->notify(new WelcomeEmailNotification(
                        $product->product->welcomeEmail,
                        $subscription->merchant,
                        $customer
                    ));
                }
            });
    }
}
