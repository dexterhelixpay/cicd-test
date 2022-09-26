<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\PaymentStatus;

class OrderNotificationService
{
    /**
     * Create order notifications from the given order combinations.
     *
     * @param  \App\Models\Merchant|null  $merchant
     * @param  string  $notificationType
     * @param  array  $purchaseType
     * @param  array  $copies
     * @param  array|null  $subscriptionType
     * @param  array|null  $applicableOrders
     * @param  int|null  $daysFromBillingDate
     * @param  array|null  $recurrences
     * @param  bool|null  $isPaymentSuccessful
     * @param  bool|null  $hasPaymentLapsed
     * @return \Illuminate\Support\Collection<int, \App\Models\OrderNotification>
     */
    public function create(
        ?Merchant $merchant,
        string $notificationType,
        array $purchaseType,
        array $copies,
        ?array $subscriptionType = null,
        ?array $applicableOrders = null,
        ?int $daysFromBillingDate = null,
        ?array $recurrences = null,
        ?bool $isPaymentSuccessful = null,
        ?bool $hasPaymentLapsed = null,
    ) {
        $query = $merchant
            ? $merchant->orderNotifications()
            : OrderNotification::query()->whereNull('merchant_id');

        $combinations = $this->getNotificationCombinations(
            $notificationType,
            $purchaseType,
            $subscriptionType,
            $applicableOrders
        );

        return $combinations
            ->map(function ($combination) use (
                $query, $copies, $daysFromBillingDate, $recurrences, $isPaymentSuccessful, $hasPaymentLapsed
            ) {
                return $query->firstOrNew(
                    $combination + [
                        'days_from_billing_date' => $daysFromBillingDate,
                        'recurrences' => $recurrences,
                        'is_payment_successful' => $isPaymentSuccessful,
                        'has_payment_lapsed' => $hasPaymentLapsed,
                    ],
                    $copies
                );
            })
            ->filter(function (OrderNotification $notification) {
                if (
                    $notification->notification_type === OrderNotification::NOTIFICATION_REMINDER
                    && !is_null($notification->days_from_billing_date)
                ) {
                    if (
                        $notification->purchase_type === OrderNotification::PURCHASE_SINGLE
                        && $notification->days_from_billing_date <= 0
                    ) {
                        return false;
                    }

                    if (
                        $notification->purchase_type === OrderNotification::PURCHASE_SUBSCRIPTION
                        && $notification->subscription_type === OrderNotification::SUBSCRIPTION_AUTO_CHARGE
                        && $notification->days_from_billing_date === 0
                    ) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->map(function (OrderNotification $notification) {
                return tap($this->fillNotificationDefaults($notification))->save();
            });
    }

    /**
     * Get the notification combinations for the given parameters.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getNotificationCombinations(
        string $notificationType,
        array $purchaseType,
        ?array $subscriptionType,
        ?array $applicableOrders,
    ) {
        $combinations = collect($notificationType)
            ->crossJoin(...array_filter([
                $purchaseType,
                $subscriptionType,
                $applicableOrders,
            ]));

        return $combinations
            ->map(function ($combination) {
                if ($combination[1] === OrderNotification::PURCHASE_SINGLE) {
                    $combination = array_slice($combination, 0, 2);
                }

                return collect($combination)
                    ->mapWithKeys(function ($value, $index) {
                        return match ($index) {
                            0 => ['notification_type' => $value],
                            1 => ['purchase_type' => $value],
                            2 => ['subscription_type' => $value],
                            3 => ['applicable_orders' => $value],
                        };
                    })
                    ->toArray();
            })
            ->unique()
            ->values();
    }

    /**
     * Get the payment notification for the given order.
     *
     * @param  \App\Models\Order  $order
     * @return \App\Models\OrderNotification|null
     */
    public function getPaymentNotificationForOrder(Order $order)
    {
        if ($merchant = $order->subscription?->merchant) {
            $query = $merchant->orderNotifications();
        } else {
            $query = OrderNotification::query()->whereNull('merchant_id');
        }

        $purchaseType = $order->isSingle()
            ? OrderNotification::PURCHASE_SINGLE
            : OrderNotification::PURCHASE_SUBSCRIPTION;

        $query
            ->where('notification_type', OrderNotification::NOTIFICATION_PAYMENT)
            ->where('purchase_type', $purchaseType)
            ->when(
                $purchaseType === OrderNotification::PURCHASE_SUBSCRIPTION,
                function ($query) use ($order) {
                    $subcriptionType = $order->is_auto_charge
                        ? OrderNotification::SUBSCRIPTION_AUTO_CHARGE
                        : OrderNotification::SUBSCRIPTION_AUTO_REMIND;

                    $applicableOrders = $order->isInitial()
                        ? OrderNotification::ORDER_FIRST
                        : OrderNotification::ORDER_SUCCEEDING;

                    $query
                        ->where('subscription_type', $subcriptionType)
                        ->where('applicable_orders', $applicableOrders);
                }
            );

        switch ((int) $order->payment_status_id) {
            case PaymentStatus::PAID:
                return $query
                    ->where('is_payment_successful', true)
                    ->first();

            case PaymentStatus::NOT_INITIALIZED:
            case PaymentStatus::PENDING:
            case PaymentStatus::INCOMPLETE:
            case PaymentStatus::FAILED:
                return $query
                    ->where('is_payment_successful', false)
                    ->where('has_payment_lapsed', $order->has_payment_lapsed)
                    ->first();

            default:
                return null;
        }
    }

    /**
     * Fill all empty notification attributes with defaults.
     *
     * @param  \App\Models\OrderNotification  $notification
     * @return \App\Models\OrderNotification
     */
    public function fillNotificationDefaults(OrderNotification $notification)
    {
        static $defaults = null;

        if (!$defaults) {
            $defaults = OrderNotification::whereNull('merchant_id')->get();
        }

        $default = $defaults
            ->where('notification_type', $notification->notification_type)
            ->where('purchase_type', $notification->purchase_type)
            ->where('subscription_type', $notification->subscription_type)
            ->where('applicable_orders', $notification->applicable_orders)
            ->where('is_payment_successful', $notification->is_payment_successful)
            ->where('has_payment_lapsed', $notification->has_payment_lapsed)
            ->when(is_null($notification->days_from_billing_date), function ($query) {
                return $query->whereNull('days_from_billing_date');
            })
            ->when($notification->days_from_billing_date < 0, function ($query) {
                return $query->where('days_from_billing_date', '<', 0);
            })
            ->when($notification->days_from_billing_date === 0, function ($query) {
                return $query->where('days_from_billing_date', 0);
            })
            ->when($notification->days_from_billing_date > 0, function ($query) {
                return $query->where('days_from_billing_date', '>', 0);
            })
            ->first();

        if ($default) {
            $notification->fill([
                'subject' => $notification->subject ?? $default->subject,
                'headline' => $notification->headline ?? $default->headline,
                'subheader' => $notification->subheader ?? $default->subheader,

                'payment_headline' => $notification->payment_headline
                    ?? $default->payment_headline,
                'payment_instructions' => $notification->payment_instructions
                    ?? $default->payment_instructions,
                'payment_button_label' => $notification->payment_button_label
                    ?? $default->payment_button_label,

                'total_amount_label' => $notification->total_amount_label
                    ?? $default->total_amount_label,

                'payment_instructions_headline' => $notification->payment_instructions_headline
                    ?? $default->payment_instructions_headline,
                'payment_instructions_subheader' => $notification->payment_instructions_subheader
                    ?? $default->payment_instructions_subheader,
            ]);
        }

        return $notification;
    }
}
