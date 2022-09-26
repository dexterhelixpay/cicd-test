<?php

namespace Database\Seeders;

use App\Imports\OrderNotifications;
use App\Models\Merchant;
use App\Models\MerchantFollowUpEmail;
use App\Models\OrderNotification;
use App\Services\OrderNotificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrderNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        OrderNotification::truncate();

        $worksheet = (new OrderNotifications)
            ->toArray('seeders/OrderNotificationSeeder.xlsx', 'database');

        $notifications = collect(Arr::first($worksheet))
            ->map(fn (array $row) => $this->createNotificationFromRow($row))
            ->flatten(1);

        Merchant::query()
            ->with('followUpEmails')
            ->whereDoesntHave('orderNotifications')
            ->cursor()
            ->tapEach(function (Merchant $merchant) use ($notifications) {
                $callback = function () use ($merchant, $notifications) {
                    if ($merchant->followUpEmails->isEmpty()) {
                        return $notifications
                            ->each(function (OrderNotification $notification) use ($merchant) {
                                $this->createMerchantNotification($notification, $merchant);
                            });
                    }

                    $notifications
                        ->where('notification_type', OrderNotification::NOTIFICATION_PAYMENT)
                        ->each(function (OrderNotification $notification) use ($merchant) {
                            $this->createMerchantNotification($notification, $merchant);
                        });

                    $merchant->followUpEmails
                        ->each(function (MerchantFollowUpEmail $email) use (
                            $notifications, $merchant
                        ) {
                            $days = (int) $email->days;

                            $notifications
                                ->where('notification_type', OrderNotification::NOTIFICATION_REMINDER)
                                ->when($days < 0, function ($notifications) {
                                    return $notifications
                                        ->where('days_from_billing_date', '<', 0);
                                })
                                ->when($days === 0, function ($notifications) {
                                    return $notifications
                                        ->where('days_from_billing_date', 0);
                                })
                                ->when($days > 0, function ($notifications) {
                                    return $notifications
                                        ->where('days_from_billing_date', '>', 0);
                                })
                                ->each(function (OrderNotification $notification) use ($email, $days, $merchant) {
                                    $notification = (clone $notification)
                                        ->fill([
                                            'days_from_billing_date' => $days,
                                            'subject' => $email->subject,
                                            'headline' => $email->headline,
                                            'subheader' => $email->body,
                                        ]);

                                    $this->createMerchantNotification($notification, $merchant);
                                });
                        });
                };

                DB::transaction($callback);
            })
            ->all();
    }

    /**
     * Create a merchant equivalent of the given notification.
     *
     * @param  \App\Models\OrderNotification  $notification
     * @param  \App\Models\Merchant  $merchant
     * @return \App\Models\OrderNotification
     */
    public function createMerchantNotification(
        OrderNotification $notification, Merchant $merchant
    ) {
        $notification = $merchant->orderNotifications()
            ->make()
            ->fill(Arr::except($notification->toArray(), ['id', 'merchant_id']));

        if ($notification->payment_button_label) {
            if (
                $notification->purchase_type === OrderNotification::PURCHASE_SUBSCRIPTION
                && $notification->applicable_orders === OrderNotification::ORDER_FIRST
                && $merchant->recurring_button_text
            ) {
                $notification->payment_button_label = $merchant->recurring_button_text;
            } elseif ($merchant->pay_button_text) {
                $notification->payment_button_label = $merchant->pay_button_text;
            }
        }

        return tap($notification)->save();
    }

    /**
     * Create an order notification from the given row.
     *
     * @param  array  $row
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Support\Collection<id, \App\Models\OrderNotification>
     */
    public function createNotificationFromRow(array $row, ?Merchant $merchant = null)
    {
        $copies = Arr::only($row, [
            'subject',
            'headline',
            'subheader',

            'payment_headline',
            'payment_instructions',
            'payment_button_label',

            'total_amount_label',

            'payment_instructions_headline',
            'payment_instructions_subheader',
        ]);

        $purchaseType = $row['purchase_type'];
        $applicableOrders = $row['applicable_orders'] ?? null;

        if ($merchant && ($copies['payment_button_label'] ?? null)) {
            if (
                $purchaseType === OrderNotification::PURCHASE_SUBSCRIPTION
                && $applicableOrders === OrderNotification::ORDER_FIRST
                && $merchant->recurring_button_text
            ) {
                $copies['payment_button_label'] = $merchant->recurring_button_text;
            } elseif ($merchant->pay_button_text) {
                $copies['payment_button_label'] = $merchant->pay_button_text;
            }
        }

        return (new OrderNotificationService)->create(
            merchant: $merchant,
            notificationType: $row['notification_type'],
            purchaseType: (array) $purchaseType,
            subscriptionType: (array) ($row['subscription_type'] ?? null),
            applicableOrders: (array) $applicableOrders,
            daysFromBillingDate: $row['days_from_billing_date'] ?? null,
            isPaymentSuccessful: $row['is_payment_successful'] ?? null,
            hasPaymentLapsed: $row['has_payment_lapsed'] ?? null,
            copies: $copies
        );
    }
}
