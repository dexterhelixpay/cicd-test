<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubscriptionReassign extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "subscription:reassign
        {--subscription=* : The subscriptions to reassign}
        {--customer= : The customer to reassign the subscriptions to}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign the given subscriptions to the given customer';

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
        if (!$customerId = $this->option('customer')) {
            return $this->getOutput()->error('--customer option is required');
        }

        /** @var \App\Models\Customer|null */
        $customer = Customer::query()
            ->with([
                'cards' => function ($query) {
                    $query->whereNotNull('verified_at');
                },
                'wallets' => function ($query) {
                    $query->whereNotNull('verified_at');
                },
            ])
            ->find($customerId);

        if (!$customer) {
            return $this->getOutput()->error('Customer not found');
        }

        $subscriptions = Subscription::query()
            ->with(['customer', 'paymentType', 'orders' => function ($query) {
                $query->satisfied(false);
            }])
            ->whereKey($this->option('subscription'))
            ->get();

        if ($subscriptions->isEmpty()) {
            return $this->getOutput()->error('No subscriptions found');
        }

        $this->getOutput()->note('Customer');

        $this->table(['Key', 'Value'], assoc_table($customer->only('id', 'name')));

        $this->getOutput()->note('Subscriptions to Reassign');

        $this->table([
            'Subscription ID',
            'Customer',
            'Payment Type',
            'Card Type',
            'Card Masked Pan',
            'Pending Orders',
        ], $subscriptions->map(function (Subscription $subscription) {
            return [
                $subscription->getKey(),
                "{$subscription->customer_id} - {$subscription->customer->name}",
                $subscription->paymentType->name,
                $subscription->paymaya_card_type ?? 'N/A',
                $subscription->paymaya_masked_pan ?? 'N/A',
                $subscription->orders->count(),
            ];
        }));

        if (!$this->confirm('Are you sure you want to reassign these subscriptions?')) {
            return 0;
        }

        DB::transaction(function () use ($subscriptions, $customer) {
            $assignedSubscriptions = $subscriptions
                ->map(function (Subscription $subscription) use ($customer) {
                    if ($subscription->customer->is($customer)) {
                        return;
                    }

                    $subscription->customer()->associate($customer);

                    if ($subscription->payment_type_id == PaymentType::CARD) {
                        /** @var \App\Models\CustomerCard|null */
                        $card = $customer->cards
                            ->sortByDesc(function (CustomerCard $card) use ($subscription) {
                                return $card->card_type == $subscription->paymaya_card_type
                                    && $card->masked_pan == $subscription->paymaya_masked_pan;
                            })
                            ->first();

                        if (!$card) {
                            return;
                        }

                        $subscription->forceFill([
                            'paymaya_card_type' => $card->card_type,
                            'paymaya_masked_pan' => $card->masked_pan,
                            'paymaya_card_token_id' => $card->card_token_id,
                        ])->saveQuietly();

                        $subscription->orders->each(function (Order $order) use ($subscription) {
                            $order->forceFill([
                                'paymaya_card_type' => $subscription->paymaya_card_type,
                                'paymaya_masked_pan' => $subscription->paymaya_masked_pan,
                                'paymaya_card_token_id' => $subscription->paymaya_card_token_id,
                            ])->saveQuietly();
                        });

                        return $subscription->fresh(['customer', 'paymentType']);
                    }
                })
                ->filter();

            if ($assignedSubscriptions->isEmpty()) {
                return;
            }

            $this->getOutput()->note('Subscriptions Reassigned');

            $this->table([
                'Subscription ID',
                'Customer',
                'Payment Type',
                'Card Type',
                'Card Masked Pan',
            ], $assignedSubscriptions->map(function (Subscription $subscription) {
                return [
                    $subscription->getKey(),
                    "{$subscription->customer_id} - {$subscription->customer->name}",
                    $subscription->paymentType->name,
                    $subscription->paymaya_card_type ?? 'N/A',
                    $subscription->paymaya_masked_pan ?? 'N/A',
                ];
            }));
        });

        return 0;
    }
}
