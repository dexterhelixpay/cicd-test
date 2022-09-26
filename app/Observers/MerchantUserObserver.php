<?php

namespace App\Observers;

class MerchantUserObserver
{
    /**
     * Handle the merchant user "updating" event.
     *
     * @param  \App\Models\MerchantUser  $merchantUser
     * @return void
     */
    public function updated($merchantUser)
    {
        $this->syncMerchantInfo($merchantUser);
    }


    /**
     * Handle the merchant user "updating" event.
     *
     * @param  \App\Models\MerchantUser  $merchantUser
     * @return void
     */
    public function creating($merchantUser)
    {
        $this->setFormattedMobileNumber($merchantUser);
    }

    /**
     * Handle the merchant user "updating" event.
     *
     * @param  \App\Models\MerchantUser  $merchantUser
     * @return void
     */
    public function updating($merchantUser)
    {
        $this->setFormattedMobileNumber($merchantUser);
    }

     /**
     * Update the formatted mobile number
     *
     * @param  \App\Models\MerchantUser  $merchantUser
     * @return void
     */
    protected function setFormattedMobileNumber($merchantUser)
    {
        if (
            !$merchantUser->isDirty(['mobile_number', 'country_id'])
        ) return;

        $country = $merchantUser->country()->first();

        $merchantUser->formatted_mobile_number = $country
            ? "{$country->dial_code}{$merchantUser->mobile_number}"
            : $merchantUser->mobile_number;
    }

    /**
     * Sync the user info to merchant info
     *
     * @param  \App\Models\MerchantUser  $merchantUser
     * @return void
     */
    protected function syncMerchantInfo($merchantUser)
    {
        if (
            $merchantUser->wasChanged(['email','mobile_number','username'])
            && $merchantUser->hasRole('Owner')
        ) {
            $merchant = $merchantUser->merchant;

            $merchant->forceFill($merchantUser->only(['email', 'mobile_number', 'username']))
                ->update();
        }
    }
}
