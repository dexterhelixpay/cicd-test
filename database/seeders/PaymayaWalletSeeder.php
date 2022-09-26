<?php

namespace Database\Seeders;

use App\Libraries\Image;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymayaWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path = 'images/payment_types/paymaya.png';

        $icon = file_get_contents(database_path("seeders/{$path}"));

        $image = (new Image($icon))->resize(240, 240);
        $image->encode('png');
        $image->put($path);

        PaymentType::create([
            'name' => 'Paymaya Wallet',
            'description' => 'We will automatically charge your wallet for each payment. You can cancel your subscription anytime.',
            'is_enabled' => 1,
            'icon_path' => $path,
        ]);
    }
}
