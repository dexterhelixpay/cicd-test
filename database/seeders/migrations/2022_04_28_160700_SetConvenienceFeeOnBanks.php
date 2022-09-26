<?php

use App\Models\Merchant;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetConvenienceFeeOnBanks_2022_04_28_160700 extends Seeder
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
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    if (
                        $bankPaymentMethod = $merchant->paymentTypes()
                            ->where('payment_type_id',PaymentType::BANK_TRANSFER)
                            ->first()
                    ) {
                        $banks = json_decode($bankPaymentMethod->pivot->payment_methods);

                        collect($banks)->each(function ($bank){
                            $bank->convenience_label = 'Convenience Fee';
                            $bank->convenience_fee = null;
                            $bank->convenience_type_id = null;
                        });

                        $merchant->paymentTypes()
                            ->updateExistingPivot(
                                PaymentType::BANK_TRANSFER,
                                ['payment_methods' => json_encode($banks)]
                            );
                    }
                })
                ->all();
        });
    }
}
