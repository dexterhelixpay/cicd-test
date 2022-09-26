<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentErrorResponseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PaymentType::query()
            ->whereKeyNot(PaymentType::CASH)
            ->get()
            ->each(function (PaymentType $paymentType) {
                $title = $paymentType->getKey() === PaymentType::CARD
                    ? 'Your Card Failed!'
                    : 'Payment Unsuccessful!';

                $paymentType->errorResponses()->make()
                    ->forceFill([
                        'title' => $title,
                        'subtitle' => 'Please change your payment method to continue with your subscription.',
                        'is_enabled' => true,
                        'is_default' => true,
                    ])
                    ->save();
            });
    }
}
