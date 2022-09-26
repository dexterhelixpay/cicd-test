<?php


use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetInternationalShippingMethod_2022_08_10_082500 extends Seeder
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
                ->where('has_shippable_products', true)
                ->cursor()
                ->each(function (Merchant $merchant) {
                    if ($shippingMethod = $merchant->shippingMethods()->where('name', 'International Delivery')->first()) {
                        $shippingMethod->update(['is_default' => true ]);
                        return;
                    }
                    $merchant->shippingMethods()->create([
                            'name' => 'International Delivery',
                            'description' => 'The merchant will coordinate the shipping for each subscription delivery.',
                            'price' => 0,
                            'is_enabled' => false,
                            'is_default' => true,
                        ]);
                })->all();
        });
    }
}
