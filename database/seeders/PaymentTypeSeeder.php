<?php

namespace Database\Seeders;

use App\Libraries\Image;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        collect([
            'Gcash' => 'images/payment_types/gcash.png',
            'GrabPay' => 'images/payment_types/grabpay.png',
            'Credit/Debit Card' => 'images/payment_types/card.png',
            'Cash' => null,
            'Bank Transfer' => null,
            'Paymaya Wallet' => 'images/payment_types/paymaya.png'
        ])->each(function ($path, $name) {
            if ($path) {
                $icon = file_get_contents(database_path("seeders/{$path}"));

                $image = (new Image($icon))->resize(240, 240);
                $image->encode('png');
                $image->put($path);
            }

            PaymentType::create([
                'name' => $name,
                'description' => 'You will be asked to pay after the service',
                'is_enabled' => in_array($name, ['Credit/Debit Card', 'Bank Transfer', 'Paymaya Wallet']),
                'icon_path' => $path,
            ]);
        });
    }
}
