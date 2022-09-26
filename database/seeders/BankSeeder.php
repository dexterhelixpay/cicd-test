<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        collect([
            'BDO' => [
                'global' => [
                    'code' => 'BDO_PERSONAL',
                    'image_path' => 'images/brankas/bdo.svg',

                    'min_value' => 100,
                    'max_value' => 10000,

                    'daily_limit' => 50000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => '_'
                ],
                'instapay' => [
                    'code' => 'BDO_PERSONAL',
                    'image_path' => 'images/brankas/bdo.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 50000,
                    'fee' => 25,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_INSTAPAY'
                ],
                'pesonet' => [
                    'code' => 'BDO_PERSONAL',
                    'image_path' => 'images/brankas/bdo.svg',

                    'min_value' => 1,
                    'max_value' => null,

                    'daily_limit' => null,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_PESONET'
                ],
            ],
            'BPI' => [
                'global' => [
                    'code' => 'BPI_PERSONAL',
                    'image_path' => 'images/brankas/bpi.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 50000,
                    'fee' => 10,

                    'no_of_free_transactions' => null,
                    'payment_channel' => '_'
                ],
                'instapay' => [
                    'code' => 'BPI_PERSONAL',
                    'image_path' => 'images/brankas/bpi.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 50000,
                    'fee' => 25,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_INSTAPAY'
                ],
                'pesonet' => [
                    'code' => 'BPI_PERSONAL',
                    'image_path' => 'images/brankas/bpi.svg',

                    'min_value' => 1,
                    'max_value' => null,

                    'daily_limit' => null,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_PESONET'
                ],
            ],
            'Metrobank' => [
                'global' => [
                    'code' => 'METROBANK_PERSONAL',
                    'image_path' => 'images/brankas/metrobank.svg',

                    'min_value' => 1,
                    'max_value' => null,

                    'daily_limit' => 50000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => '_'
                ],
                'instapay' => [
                    'code' => 'METROBANK_PERSONAL',
                    'image_path' => 'images/brankas/metrobank.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 50000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_INSTAPAY'
                ],
                'pesonet' => [
                    'code' => 'METROBANK_PERSONAL',
                    'image_path' => 'images/brankas/metrobank.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 200000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_PESONET'
                ]
            ],
            'RCBC' => [
                'global' => [
                    'code' => 'RCBC_PERSONAL',
                    'image_path' => 'images/brankas/rcbc.svg',

                    'min_value' => 1,
                    'max_value' => 499999,

                    'daily_limit' => 500000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => '_'
                ],
                'instapay' => [
                    'code' => 'RCBC_PERSONAL',
                    'image_path' => 'images/brankas/rcbc.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => null,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_INSTAPAY'
                ],
                'pesonet' => [
                    'code' => 'RCBC_PERSONAL',
                    'image_path' => 'images/brankas/rcbc.svg',

                    'min_value' => 1,
                    'max_value' => 499999,

                    'daily_limit' => 499999,
                    'fee' => 10,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_PESONET'
                ]
            ],
            'PNB' => [
                'global' => [
                    'code' => 'PNB_PERSONAL',
                    'image_path' => 'images/brankas/pnb.svg',

                    'min_value' => 1,
                    'max_value' => null,

                    'daily_limit' => 200000,
                    'fee' => null,

                    'no_of_free_transactions' => 3,
                    'payment_channel' => '_'
                ],
                'instapay' => [
                    'code' => 'PNB_PERSONAL',
                    'image_path' => 'images/brankas/pnb.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => null,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_INSTAPAY'
                ],
                'pesonet' => [
                    'code' => 'PNB_PERSONAL',
                    'image_path' => 'images/brankas/pnb.svg',

                    'min_value' => 1,
                    'max_value' => 100000,

                    'daily_limit' => 600000,
                    'fee' => 30,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_PESONET'
                ]
            ],
            'UnionBank' => [
                'global' => [
                    'code' => 'UNIONBANK_PERSONAL',
                    'image_path' => 'images/brankas/unionbank.svg',

                    'min_value' => 1,
                    'max_value' => 500000,

                    'daily_limit' => 500000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => '_'
                ],
                'instapay' => [
                    'code' => 'UNIONBANK_PERSONAL',
                    'image_path' => 'images/brankas/unionbank.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 500000,
                    'fee' => 10,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_INSTAPAY'
                ],
                'pesonet' => [
                    'code' => 'UNIONBANK_PERSONAL',
                    'image_path' => 'images/brankas/unionbank.svg',

                    'min_value' => 1,
                    'max_value' => 50000,

                    'daily_limit' => 500000,
                    'fee' => null,

                    'no_of_free_transactions' => null,
                    'payment_channel' => 'PH_PESONET'
                ]
            ]
        ])->each(function ($bank, $name) {
            if ($bank['global']['image_path']) {
                $icon = file_get_contents(database_path("seeders/{$bank['global']['image_path']}"));

                Storage::put($bank['global']['image_path'], $icon);
            }

            foreach ($bank as $channel) {
                Bank::create([
                    'name' => $name,
                    'code' => $channel['code'],

                    'image_path' => $channel['image_path'],

                    'min_value' => $channel['min_value'],
                    'max_value' => $channel['max_value'],

                    'daily_limit' => $channel['daily_limit'],
                    'fee' => $channel['fee'],

                    'no_of_free_transactions' => $channel['no_of_free_transactions'],
                    'payment_channel' => $channel['payment_channel'],

                    'is_enabled' => true,
                ]);
            }
        });
    }
}
