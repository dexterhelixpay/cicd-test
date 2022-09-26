<?php

namespace App\Console\Commands\Subscription;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use App\Models\Subscription;
use Illuminate\Console\Command;

class SubscriptionForget extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:forget
        {--payment-type=* : Forget subscriptions with the given payment types}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Forget certain attributes of subscriptions';

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
        $options = collect($this->options())
            ->only(['payment-type'])
            ->filter()
            ->map(function ($value, $key) {
                switch ($key) {
                    case 'payment-type':
                        return PaymentType::whereKey($value)->get()->modelKeys();

                    default:
                        return $value;
                }
            })
            ->filter();

        if ($options->isEmpty()) {
            return $this->getOutput()->error('At least one option is required.');
        }

        $query = Subscription::query();

        if ($paymentTypes = $options->get('payment-type', [])) {
            $query
                ->with(['orders' => function ($query) use ($paymentTypes) {
                    $query
                        ->whereIn('payment_type_id', $paymentTypes)
                        ->whereIn('order_status_id', [
                            OrderStatus::UNPAID,
                            OrderStatus::INCOMPLETE,
                            OrderStatus::FAILED,
                        ]);
                }])
                ->where(function ($query) use ($paymentTypes) {
                    $query
                        ->whereNull('cancelled_at')
                        ->whereNull('completed_at')
                        ->where(function ($query) use ($paymentTypes) {
                            $query
                                ->whereIn('payment_type_id', $paymentTypes)
                                ->whereHas('orders', function ($query) use ($paymentTypes) {
                                    $query
                                        ->whereIn('payment_type_id', $paymentTypes)
                                        ->whereIn('order_status_id', [
                                            OrderStatus::UNPAID,
                                            OrderStatus::INCOMPLETE,
                                            OrderStatus::FAILED,
                                        ]);
                                });
                        });
                });
        }

        if (!$count = $query->count()) {
            return $this->getOutput()->success('No subscriptions found.');
        }

        if (!$this->confirm("{$count} subscriptions found. Continue?")) {
            return;
        }

        $bar = $this->getOutput()->createProgressBar($count);

        $query->get()->each(function (Subscription $subscription) use ($options, $bar) {
            if ($options->get('payment-type')) {
                $subscription->paymentType()->dissociate();
            }

            $subscription->save();

            $subscription->orders->each(function (Order $order) use ($options) {
                if ($options->get('payment-type')) {
                    $order->paymentType()->dissociate();
                }

                $order->save();
            });

            $bar->advance();
        });

        $bar->finish();

        $this->line("\n");
        $this->getOutput()->success('Subscriptions updated.');

        return Command::SUCCESS;
    }
}
