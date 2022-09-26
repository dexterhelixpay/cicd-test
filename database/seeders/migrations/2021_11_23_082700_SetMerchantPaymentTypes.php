<?php

use App\Models\Merchant;
use App\Models\Bank;
use App\Models\PaymentType;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class SetMerchantPaymentTypes_2021_11_23_082700 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $settings = [
                [
                    'key' => 'IsGcashEnabled',
                    'value' => setting('IsDigitalWalletPaymentEnabled', true),
                    'value_type' => 'boolean',
                ],
                [
                    'key' => 'IsGrabPayEnabled',
                    'value' => setting('IsDigitalWalletPaymentEnabled', true),
                    'value_type' => 'boolean',
                ],
            ];

            collect($settings)->each(function ($setting) {
                Setting::updateOrCreate([
                    'key' => $setting['key'],
                ], Arr::except($setting, 'key'));
            });

            Merchant::query()
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $paymentTypes = [
                        [
                            'id' => 3,
                            'name' => 'Credit/Debit Card',
                            'is_enabled' => setting('IsCcPaymentEnabled', true),
                            'is_globally_enabled' => setting('IsCcPaymentEnabled', true),
                            'sort_number' => 1
                        ],
                        [
                            'id' => 6,
                            'name' => 'Paymaya Wallet',
                            'is_enabled' => setting('IsPaymayaWalletEnabled', true),
                            'is_globally_enabled' => setting('IsPaymayaWalletEnabled', true),
                            'sort_number' => 2
                        ],
                        [
                            'id' => 5,
                            'name' => 'Bank Transfer',
                            'is_enabled' => setting('IsBankTransferEnabled', true),
                            'is_globally_enabled' => setting('IsBankTransferEnabled', true),
                            'sort_number' => 3,
                            'payment_methods' => Bank::where('payment_channel', '_')
                                ->get()
                                ->map(function(Bank $bank) {
                                    return [
                                        'name' => $bank->name,
                                        'code' => $bank->code,
                                        'is_enabled' => $bank->is_enabled,
                                        'is_globally_enabled' => $bank->is_enabled,
                                        'image_path' => $bank->image_path
                                    ];
                                })
                        ],
                        [
                            'id' => 1,
                            'name' => 'Gcash',
                            'is_enabled' => setting('isGcashEnabled', true),
                            'is_globally_enabled' => setting('isGcashEnabled', true),
                            'sort_number' => 4
                        ],
                        [
                            'id' => 2,
                            'name' => 'GrabPay',
                            'is_enabled' => setting('isGrabPayEnabled', true),
                            'is_globally_enabled' => setting('isGrabPayEnabled', true),
                            'sort_number' => 5
                        ]
                    ];

                    collect($paymentTypes)
                        ->each(function ($paymentType) use($merchant) {
                            $merchant->paymentTypes()->attach(
                                $paymentType['id'],
                                Arr::only($paymentType, [
                                    'is_enabled',
                                    'is_globally_enabled',
                                    'sort_number',
                                    'payment_methods'
                                ])
                            );
                        });
                })
                ->all();
        });
    }
}
