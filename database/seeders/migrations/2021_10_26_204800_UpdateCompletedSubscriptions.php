<?php

use App\Models\OrderStatus;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class UpdateCompletedSubscriptions_2021_10_26_204800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Subscription::query()
            ->whereNotNull('cancelled_at')
            ->whereDoesntHave('orders', function ($query) {
                $query->whereIn('order_status_id', [
                    OrderStatus::FAILED,
                    OrderStatus::CANCELLED,
                ]);
            })
            ->cursor()
            ->tapEach(function (Subscription $subscription) {
                $subscription
                    ->forceFill([
                        'cancelled_at' => null,
                        'completed_at' => $subscription->cancelled_at,
                    ])
                    ->saveQuietly();
            })
            ->all();
    }
}
