<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (!app()->isProduction()) {
            DB::transaction(function () {
                Merchant::query()
                    ->whereHas('products', function ($query) {
                        $query->where('is_visible', true);
                    })
                    ->get()
                    ->each(function (Merchant $merchant) {
                        Subscription::factory(random_int(1, 5))->create([
                            'merchant_id' => $merchant->getKey()
                        ]);
                    });
            });
        }
    }
}
