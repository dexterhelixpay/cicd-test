<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MerchantButtonsSeeder extends Seeder
{


    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Merchant::query()
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                DB::transaction(function () use ($merchant) {
                    $merchant->setAttribute(
                        'buttons',
                        [
                            'pay_button' => [
                                'label' => 'Pay Now',
                                'css' => [
                                    'background-color' => null
                                ]
                            ],
                            'recurring_button' => [
                                'label' => 'Start Subscription',
                                'css' => [
                                    'background-color' => null
                                ]
                            ],
                            'product_details_button' => [
                                'label' => 'Details',
                                'css' => [
                                    'background-color' => $merchant->product_details_button_color
                                        ?: null
                                ]
                            ],
                            'product_select_button' => [
                                'label' => 'Select',
                                'css' => [
                                    'background-color' => $merchant->product_select_button_color
                                        ?: null
                                ]
                            ],
                            'checkout_button' => [
                                'label' => 'Checkout',
                                'css' => [
                                    'background-color' => null
                                ]
                            ],
                            'add_to_order_button' => [
                                'label' => 'Add to Order',
                                'css' => [
                                    'background-color' => null
                                ]
                            ],
                        ]
                    );
                    $merchant->save();
                });
            })
            ->all();
    }
}
