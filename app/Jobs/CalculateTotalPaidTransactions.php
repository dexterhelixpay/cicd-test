<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CalculateTotalPaidTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The merchant IDs.
     *
     * @var int[]
     */
    protected $merchantId;

    /**
     * Create a new job instance.
     *
     * @param  string|array  $merchantId
     * @return void
     */
    public function __construct($merchantId)
    {
        $this->merchantId = (array) $merchantId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Merchant::query()
            ->whereKey($this->merchantId)
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                $merchant->total_paid_transactions = $merchant->orders()
                    ->where('order_status_id', OrderStatus::PAID)
                    ->sum('orders.total_price');

                $merchant->save();
            })
            ->all();
    }
}
