<?php

use App\Models\PaymentErrorResponse;
use Illuminate\Database\Seeder;

class AddPlaceholdersToPaymentErrorResponses_2022_07_04_153200 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        PaymentErrorResponse::query()
            ->where('subtitle', 'like', '%subscription%')
            ->get()
            ->each(function (PaymentErrorResponse $response) {
                $subtitle = preg_replace(
                    '/continue(?!})/',
                    '{startOrContinue}',
                    $response->subtitle
                );

                $subtitle = preg_replace(
                    '/(?<!{)subscription/',
                    '{subscriptionTermSingular}',
                    $subtitle
                );

                $response->update(compact('subtitle'));
            });
    }
}
