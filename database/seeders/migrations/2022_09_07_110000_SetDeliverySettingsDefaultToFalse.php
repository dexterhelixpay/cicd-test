<?php


use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetDeliverySettingsDefaultToFalse_2022_09_07_110000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        DB::transaction(function () {
            Merchant::query()
                ->where('is_estimated_delivery_date_enabled', true)
                ->orWhere('is_enabled_fulfillment_status', true)
                ->cursor()
                ->each(function (Merchant $merchant) {
                    $merchant->update([
                        'is_estimated_delivery_date_enabled' => false,
                        'is_enabled_fulfillment_status' => false,
                    ]);
                })->all();
        });
    }
}
