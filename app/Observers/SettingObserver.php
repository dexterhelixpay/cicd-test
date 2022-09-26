<?php

namespace App\Observers;

use App\Models\Bank;
use App\Models\MerchantPaymentType;
use App\Models\PaymentType;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class SettingObserver
{
    /**
     * Handle the setting "updated" event.
     *
     * @param  \App\Models\Setting  $setting
     * @return void
     */
    public function updated($setting)
    {
        $this->syncMerchantPaymentTypes($setting);
    }

    /**
     * Sync payment types
     *
     * @param  \App\Models\Setting  $setting
     * @return void
     */
    protected function syncMerchantPaymentTypes($setting)
    {
        if (
            $setting->wasChanged('value')
            && (
                $setting->key == 'IsDigitalWalletPaymentEnabled'
                || $setting->key == 'IsBankTransferEnabled'
                || $setting->key == 'IsGcashEnabled'
                || $setting->key == 'IsGrabPayEnabled'
                || $setting->key == 'IsCcPaymentEnabled'
                || $setting->key == 'IsPaymayaWalletEnabled'
            )
        ) {
            switch ($setting->key) {
                case 'IsBankTransferEnabled':
                    $paymentTypeIds = [PaymentType::BANK_TRANSFER];
                    break;

                case 'IsGrabPayEnabled':
                    $paymentTypeIds = [PaymentType::GRABPAY];
                    break;

                case 'IsGcashEnabled':
                    $paymentTypeIds = [PaymentType::GCASH];
                    break;

                case 'IsCcPaymentEnabled':
                    $paymentTypeIds = [PaymentType::CARD];
                    break;

                case 'IsPaymayaWalletEnabled':
                    $paymentTypeIds = [PaymentType::PAYMAYA_WALLET];
                    break;

                default:
                    $paymentTypeIds = [PaymentType::GCASH, PaymentType::GRABPAY];
            }

            MerchantPaymentType::whereIn('payment_type_id', $paymentTypeIds)
                ->update(['is_globally_enabled' => $setting->value]);
        }
    }
}
