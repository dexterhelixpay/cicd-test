<?php

use App\Jobs\CalculateTotalPaidTransactions;
use App\Models\Merchant;
use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class SetMerchantTotalPaidTransactions_2022_03_21_115500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $merchantIds = Merchant::query()
            ->whereHas('orders', function ($query) {
                $query->where('order_status_id', OrderStatus::PAID);
            })
            ->pluck('id')
            ->toArray();

        (new CalculateTotalPaidTransactions($merchantIds))->handle();
    }
}
