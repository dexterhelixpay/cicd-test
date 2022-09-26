<?php

use App\Models\Merchant;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateMerchantConvenienceFee_2022_05_05_070200 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $merchants =Merchant::query()
                ->whereNotNull('convenience_fee')
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $merchant
                        ->paymentTypes
                        ->each(function ($paymentMethod) use ($merchant) {
                            $merchant->paymentTypes()
                                ->updateExistingPivot(
                                    $paymentMethod->id,
                                    [
                                        'convenience_fee' => $merchant->convenience_fee,
                                        'convenience_label' => $merchant->convenience_label,
                                        'convenience_type_id' => $merchant->convenience_type_id,
                                    ]
                                );

                            if ($paymentMethod->id == PaymentType::BANK_TRANSFER) {
                                $banks = json_decode($paymentMethod->pivot->payment_methods);

                                collect($banks)->each(function ($bank) use ($merchant) {
                                    $bank->convenience_label = $merchant->convenience_label;
                                    $bank->convenience_fee = $merchant->convenience_fee;
                                    $bank->convenience_type_id = $merchant->convenience_type_id;
                                });

                                $merchant->paymentTypes()
                                    ->updateExistingPivot(
                                        PaymentType::BANK_TRANSFER,
                                        ['payment_methods' => json_encode($banks)]
                                    );
                            }
                        });
                })
                ->all();
        });
    }
}
