<?php

namespace App\Console\Commands\Customer;

use App\Models\Order;
use App\Models\PaymentStatus;
use App\Notifications\DynamicSmsNotification;
use Illuminate\Console\Command;

class CustomerRemind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:remind
        {--past-lapsed : Remind to disregard messages about past lapsed payments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('past-lapsed')) {
            return $this->remindCustomersWithPastLapsedPayments();
        }

        return 0;
    }

    /**
     * Remind customers to disregard messages about past lapsed payments
     *
     * @return int
     */
    public function remindCustomersWithPastLapsedPayments()
    {
        $orders = Order::query()
            ->with(['subscription' => ['customer', 'merchant']])
            ->where('payment_status_id', PaymentStatus::FAILED)
            ->where('has_payment_lapsed', true)
            ->whereDate('payment_attempted_at', '<', now()->toDateString())
            ->get()
            ->unique(function (Order $order) {
                return join('.', [
                    $order->subscription->merchant_id,
                    $order->subscription->customer_id,
                ]);
            });

        $this->withProgressBar($orders, function (Order $order) {
            $merchantName = $order->subscription->merchant->name;

            $message = join(' ', [
                "You may have received a message about an unsuccessful payment for {$merchantName}.",
                'This was in reference to an attempted payment you may have made in the past.',
                'Please disregard this notification.',
                'No need to do anything.',
                'Have a good day!',
            ]);

            $order->subscription->customer->notifyNow(
                new DynamicSmsNotification($message)
            );
        });
    }
}
