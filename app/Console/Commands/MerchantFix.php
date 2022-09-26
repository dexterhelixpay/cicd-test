<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Models\MerchantRecurrence;
use App\Models\Order;
use App\Models\OrderNotification;
use App\Models\Subscription;
use App\Models\SubscriptionCustomField;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MerchantFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "merchant:fix
        {--cascade-shippable-to-orders : Cascade the merchant's shippable status to orders}
        {--only=* : Fix merchants only with these IDs}
        {--except=* : Fix merchants except with these IDs}
        {--update-subheader-notification-single-order : Update 3 days after single notification subheader}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix problems with merchants';

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
        if ($this->option('cascade-shippable-to-orders')) {
            return $this->cascadeShippableStatusToOrders();
        }

        if ($this->option('update-subheader-notification-single-order')) {
            return $this->updateSingleOrderNotificationSubheader();
        }

        $this->deleteDuplicateRecurrences();
        $this->sortRecurrences();

        return 0;
    }

    /**
     * Update single order notification
     *
     * @return void
     */
    protected function updateSingleOrderNotificationSubheader()
    {
        OrderNotification::query()
        ->where('purchase_type', OrderNotification::PURCHASE_SINGLE)
        ->where('notification_type', OrderNotification::NOTIFICATION_REMINDER)
        ->where('days_from_billing_date', 3)
        ->where(function($query) {
            $query->where(function ($query) {
                $query->where('subheader', 'Please pay {totalPrice} for your {subscriptionTermSingular} with {merchantName} on or before {billingDate}')
                    ->orWhere('subheader', 'Please pay ₱{totalPrice} for your bill with {merchantName}.');
            })->orWhere('subheader', 'Please pay today to {﻿startOrContinue} your {subscriptionTermSingular}');
        })->update(['subheader' => 'Please pay today to complete your order']);
    }

    /**
     * Cascade merchant's shippable status to subscriptions/orders.
     *
     * @return void
     */
    protected function cascadeShippableStatusToOrders()
    {
        Merchant::query()
            ->with('subscriptions.orders')
            ->when(count($this->option('only')), function ($query) {
                $query->whereKey($this->option('only'));
            })
            ->when(count($this->option('except')), function ($query) {
                $query->whereKeyNot($this->option('except'));
            })
            ->get()
            ->each(function (Merchant $merchant) {
                $merchant->products()->update([
                    'is_shippable' => $merchant->has_shippable_products,
                ]);

                $merchant->subscriptions->each(function (Subscription $subscription) use ($merchant) {
                    $subscription->products()->update([
                        'is_shippable' => $merchant->has_shippable_products,
                    ]);

                    $subscription->orders->each(function (Order $order) use ($merchant) {
                        $order->products()->update([
                            'is_shippable' => $merchant->has_shippable_products,
                        ]);
                    });
                });
            });
    }

    /**
     * Delete duplicate merchant recurrences.
     *
     * @return void
     */
    protected function deleteDuplicateRecurrences()
    {
        $merchants = Merchant::query()
            ->with('recurrences')
            ->when(count($this->option('only')), function ($query) {
                $query->whereKey($this->option('only'));
            })
            ->when(count($this->option('except')), function ($query) {
                $query->whereKeyNot($this->option('except'));
            })
            ->get()
            ->map(function (Merchant $merchant) {
                $duplicateRecurrences = $merchant->recurrences
                    ->groupBy('code')
                    ->filter(function (Collection $collection) {
                        return $collection->count() > 1;
                    })
                    ->flatten(1);

                return $merchant->setRelation('recurrences', $duplicateRecurrences);
            })
            ->filter(function (Merchant $merchant) {
                return $merchant->recurrences->isNotEmpty();
            });

        if ($merchants->isEmpty()) {
            return $this->getOutput()
                ->success('No merchants with duplicate recurrences found.');
        }

        $message = "Merchants with Duplicate Recurrences: {$merchants->count()}";

        $this->getOutput()->warning($message);

        if (!$this->confirm('Do you want to remove the duplicate recurrences?')) {
            return;
        }

        DB::transaction(function () use ($merchants) {
            $merchants->each(function (Merchant $merchant) {
                $merchant->recurrences
                    ->groupBy('code')
                    ->each(function (Collection $recurrences) {
                        $recurrences = $recurrences->sortByDesc('is_enabled');
                        $recurrences->shift();

                        $recurrences->each->delete();
                    });
            });
        });

        $this->getOutput()->success('Duplicate recurrences removed successfully.');
    }

    /**
     * Sort all merchants' recurrences.
     *
     * @return void
     */
    protected function sortRecurrences()
    {
        Merchant::query()
            ->with('recurrences')
            ->when(count($this->option('only')), function ($query) {
                $query->whereKey($this->option('only'));
            })
            ->when(count($this->option('except')), function ($query) {
                $query->whereKeyNot($this->option('except'));
            })
            ->get()
            ->each(function (Merchant $merchant) {
                DB::transaction(function () use ($merchant) {
                    $merchant->recurrences->each(function (MerchantRecurrence $recurrence) {
                        switch ($recurrence->code) {
                            case 'single':
                                $recurrence->sort_number = 1;
                                break;

                            case 'weekly':
                                $recurrence->sort_number = 2;
                                break;

                            case 'semimonthly':
                                $recurrence->sort_number = 3;
                                break;

                            case 'monthly':
                                $recurrence->sort_number = 4;
                                break;

                            case 'quarterly':
                                $recurrence->sort_number = 5;
                                break;

                            default:
                                //
                        }

                        $recurrence->saveQuietly();
                    });

                    $merchant->recurrences()->get()->each(function ($recurrence, $index) {
                        $recurrence->setAttribute('sort_number', $index + 1)->saveQuietly();
                    });
                });
            });
    }
}
