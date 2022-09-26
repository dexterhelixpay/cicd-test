<?php

namespace App\Observers;

use App\Jobs\WebhookEvents\SubscriptionCancelled;
use App\Jobs\WebhookEvents\SubscriptionCompleted;
use App\Jobs\WebhookEvents\SubscriptionCreated;
use App\Jobs\WebhookEvents\SubscriptionUpdated;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Models\Subscription;

class SubscriptionObserver
{
    /**
     * Handle the subscription "creating" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function creating($subscription)
    {
        $this->setBillingShippingDetails($subscription);
        $this->unsetAutoChargeFlag($subscription);
    }

    /**
     * Handle the subscription "created" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function created($subscription)
    {
        $this->postUpdatesToWebhooks($subscription);
    }

    /**
     * Handle the subscription "updating" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function updating($subscription)
    {
        $this->unsetAutoChargeFlag($subscription);
    }

    /**
     * Handle the subscription "updated" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function updated($subscription)
    {
        $this->cancelOrders($subscription);
        $this->sendCancelledNotification($subscription);
        $this->cascadeCardDetails($subscription);
        $this->postUpdatesToWebhooks($subscription);
        $this->reuseVoucher($subscription);
        $this->cascadePaymentType($subscription);
        $this->cascadeSubscriptionInfo($subscription);
        $this->applyCustomShippingFee($subscription);
    }

    /**
     * Handle the subscription "deleting" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function deleting($subscription)
    {
        $this->deleteOrders($subscription);
    }

    /**
     * Handle the subscription "updated" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param
     * @return void
     */
    public function attached($subscription, $relation, $properties)
    {
        if ($relation === 'products') {
            $this->postUpdatesToWebhooks($subscription, true);
        }
    }

    /**
     * Handle the subscription "updated" event.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function detached($subscription, $relation, $properties)
    {
        if ($relation === 'products') {
            $this->postUpdatesToWebhooks($subscription, true);
        }
    }


    /**
     * Cascade payment types
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function cascadePaymentType($subscription)
    {
        if (!$subscription->wasChanged('payment_type_id')) return;

        $subscription->orders()
            ->whereNotIn('order_status_id', [
                OrderStatus::PAID,
                OrderStatus::SKIPPED
            ])
            ->get()
            ->each(function(Order $order) use($subscription) {
                $order->forceFill([
                    'payment_type_id' => $subscription->payment_type_id
                ])->saveQuietly();
            });
    }

    /**
     * Cascade subscription info to unfulfilled orders.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function cascadeSubscriptionInfo($subscription)
    {
        $fields = [
            'payor',
            'billing_address',
            'billing_barangay',
            'billing_city',
            'billing_province',
            'billing_zip_code',
            'billing_country',

            'recipient',
            'shipping_address',
            'shipping_barangay',
            'shipping_city',
            'shipping_province',
            'shipping_zip_code',
            'shipping_country',
        ];

        if (!$subscription->wasChanged($fields)) return;

        $subscription->orders()
            ->whereIn('order_status_id', [
                OrderStatus::UNPAID,
                OrderStatus::FAILED,
                OrderStatus::INCOMPLETE,
                OrderStatus::OVERDUE,
            ])
            ->get()
            ->each(function(Order $order) use ($subscription, $fields) {
                $order->forceFill($subscription->only($fields))->saveQuietly();
            });
    }

    /**
     * Reuse the vouchers and remove it to orders
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function reuseVoucher($subscription)
    {
        if (
            $subscription->wasChanged('voucher_id')
        ) {
            if (!$subscription->voucher) {
                $subscription->orders()
                    ->whereHas('voucher')
                    ->get()
                    ->each(function(Order $order) use ($subscription) {
                        $order->voucher->increment('remaining_count');
                        $order->forceFill(['voucher_code' => null])->update();
                        $order->voucher()->detach();
                        $order->voucher->restoreCustomerSlot($subscription);

                        $order->setTotalPrice();
                    });
            }

            $subscription->setTotalPrice();
        }
    }

    /**
     * Apply custom shipping fee
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function applyCustomShippingFee($subscription)
    {
        if (
            $subscription->wasChanged('custom_shipping_fee')
        ) {
            $subscription->orders()
                ->where('order_status_id',OrderStatus::UNPAID)
                ->get()
                ->each(function(Order $order) use($subscription) {
                    $order->update([
                        'custom_shipping_fee' => $subscription->custom_shipping_fee
                    ]);
                    $order->setTotalPrice();
                });

            $subscription->setTotalPrice();
        }
    }

    /**
     * Delete the orders under the given subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function deleteOrders($subscription)
    {
        $subscription->orders()->get()->each->delete();
    }

    /**
     * Post to merchant webhooks about subscription updates.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  bool  $isUpdated
     * @return void
     */
    protected function postUpdatesToWebhooks($subscription, $isUpdated = false)
    {
        if ($subscription->wasRecentlyCreated) {
            $event = new SubscriptionCreated($subscription);
        }

        if (
            $subscription->wasChanged('completed_at')
            && is_null($subscription->getOriginal('completed_at'))
        ) {
            $event = new SubscriptionCompleted($subscription);
        } elseif (
            $subscription->wasChanged('cancelled_at')
            && is_null($subscription->getOriginal('cancelled_at'))
        ) {
            $event = new SubscriptionCancelled($subscription);
        } elseif ($subscription->wasChanged() || $isUpdated) {
            $event = new SubscriptionUpdated($subscription);
        }

        if (isset($event)) {
            dispatch($event->postTo($subscription->merchant_id))->afterCommit();
        }
    }

    /**
     * Send cancellation notification to customer.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function sendCancelledNotification($subscription)
    {
        if (
            $subscription->wasChanged('cancelled_at')
            && $subscription->cancelled_at
            && is_null($subscription->getOriginal('cancelled_at'))
        ) {
            $subscription->notifyCustomer('cancelled');
        }
    }

    /**
     * Set the billing/shipping details of the subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function setBillingShippingDetails($subscription)
    {
        if (
            !$subscription->merchant->has_shippable_products
            || !$subscription->merchant->is_address_enabled
        ) {
            $subscription->fill([
                'recipient' => null,
                'shipping_address' => null,
                'shipping_province' => null,
                'shipping_city' => null,
                'shipping_barangay' => null,
                'shipping_zip_code' => null,
                'shipping_country' => null,
            ]);
        }

        if (!$subscription->merchant->is_address_enabled) {
            $subscription->fill([
                'billing_address' => null,
                'billing_province' => null,
                'billing_city' => null,
                'billing_barangay' => null,
                'billing_zip_code' => null,
                'billing_country' => null,
            ]);
        }
    }

    /**
     * Unset the auto-charge flag if the payment type is not auto chargeable.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function unsetAutoChargeFlag($subscription)
    {
        $isAutoChargeable = in_array((int) $subscription->payment_type_id, [
            PaymentType::CARD,
            PaymentType::PAYMAYA_WALLET,
        ]);

        if ($subscription->isDirty('payment_type_id') && !$isAutoChargeable) {
            $subscription->is_auto_charge = false;
        }
    }

     /**
     * Update paymaya card details to all orders.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function cascadeCardDetails($subscription)
    {
        if (!$subscription->wasChanged('paymaya_card_token_id')) return;

        $subscription->orders()
            ->where('payment_status_id', '!=', PaymentStatus::PAID)
            ->update([
                'paymaya_card_token_id' => $subscription->paymaya_card_token_id,
                'paymaya_card_type' => $subscription->paymaya_card_type,
                'paymaya_masked_pan' => $subscription->paymaya_masked_pan,
            ]);
    }

    /**
     * Cancel subscription orders.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function cancelOrders($subscription)
    {
        if ($subscription->wasChanged('cancelled_at')) {
            Subscription::withoutEvents(function () use ($subscription) {
                if ($subscription->cancelled_at) {
                    $subscription->orders()
                        ->satisfied(false)
                        ->get()
                        ->each
                        ->update(['order_status_id' => OrderStatus::CANCELLED]);
                }

                if (!$subscription->cancelled_at) {
                    $subscription->orders()
                        ->where('order_status_id',OrderStatus::CANCELLED)
                        ->get()
                        ->each
                        ->update(['order_status_id' => OrderStatus::UNPAID]);
                }
            });
        }
    }
}
