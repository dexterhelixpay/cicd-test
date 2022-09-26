<?php


use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetDefaultShippingMethod_2022_08_12_061000 extends Seeder
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
                ->cursor()
                ->each(function (Merchant $merchant) {
                    collect(['Metro Manila Delivery', 'Province Delivery'])
                        ->each(function($name) use ($merchant) {
                            $shippingMethod = $merchant->shippingMethods()
                                ->firstWhere('name', $name);
                            if ($shippingMethod) {
                                $shippingMethod->update(['is_default' => true ]);
                            }
                        });
                })->all();
        });
    }
}
