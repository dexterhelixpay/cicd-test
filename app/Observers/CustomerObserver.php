<?php

namespace App\Observers;

use App\Facades\Viber;
use App\Jobs\WebhookEvents\SubscriptionUpdated;
use App\Libraries\Shopify\Shopify;
use App\Libraries\Viber\Message as ViberMessage;
use App\Models\Country;

class CustomerObserver
{
    /**
     * Handle the customer "creating" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function creating($customer)
    {
        $this->setCountry($customer);
        $this->setFormattedMobileNumber($customer);

    }

     /**
     * Handle the customer "updating" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function updating($customer)
    {
        $this->setFormattedMobileNumber($customer);

    }

    /**
     * Handle the customer "created" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function created($customer)
    {
        $this->createShopifyCustomer($customer);
    }


    /**
     * Handle the customer "deleting" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function deleting($customer)
    {
        $this->deleteSubscriptions($customer);
    }

    /**
     * Update the formatted mobile number
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function setFormattedMobileNumber($customer)
    {
        if (
            !$customer->isDirty(['mobile_number', 'country_id'])
        ) return;

        $country = $customer->country()->first();

        $customer->formatted_mobile_number = $country
            ? "{$country->dial_code}{$customer->mobile_number}"
            : $customer->mobile_number;
    }

    /**
     * Handle the customer "deleting" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function updated($customer)
    {
        $this->postUpdatesToWebhooks($customer);
        $this->updateShopifyCustomer($customer);
        $this->createShopifyCustomer($customer);
        $this->sendWelcomeMessage($customer);
    }


    /**
     * Update shopify customer
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function updateShopifyCustomer($customer)
    {
        $merchant = $customer->merchant;

        if (
            !$customer->merchant->shopify_info
            || !$customer->shopify_id
            || !$customer->name
        ) return;

        $shopify = new Shopify(
            $merchant->shopify_domain, $merchant->shopify_info['access_token']
        );

        $shopify->updateCustomer($customer);

        $shopify->addTag(
            "gid://shopify/Customer/{$customer->shopify_id}",
            "HelixPay"
        );
    }

    /**
     * Create shopify customer
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function createShopifyCustomer($customer)
    {
        $merchant = $customer->merchant;

        if (
            !$customer->merchant->shopify_info
            || $customer->shopify_id
            || !$customer->name
        ) return;

        $shopify = new Shopify(
            $merchant->shopify_domain, $merchant->shopify_info['access_token']
        );

        $response = $shopify->createCustomer($customer);

        $customer->forceFill([
            'shopify_id' => $response->json('data.customerCreate.customer.legacyResourceId')
        ])->saveQuietly();

        $shopify->addTag(
            "gid://shopify/Customer/{$customer->shopify_id}",
            "HelixPay"
        );
    }

    /**
     * Delete the given customer's subscriptions.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function deleteSubscriptions($customer)
    {
        $customer->subscriptions()->get()->each->delete();
    }

      /**
     * Post to merchant webhooks about customer updates.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function sendWelcomeMessage($customer)
    {
        if ($customer->wasChanged('viber_info') && $customer->viber_info) {
            if ($customer->merchant->viber_key) {
                Viber::setViberCredentials(
                    $customer->merchant->viber_key,
                    $customer->merchant->name,
                    $customer->merchant->logo_image_path
                );
            }

            ViberMessage::send(
                $customer->viber_info['id'],
                "Thank you for subscribing to {$customer->merchant->name}! You will now receive real time notifications on your {$customer->merchant->subscription_term_plural} through Viber."
            );
        }
    }

    /**
     * Set the customer's country if missing.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function setCountry($customer)
    {
        if (!$customer->country_id) {
            $philippines = Country::where('code', 'PH')->first();

            $customer->country()->associate($philippines);
        }
    }

    /**
     * Post to merchant webhooks about customer updates.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    protected function postUpdatesToWebhooks($customer)
    {
        $subscription = $customer->subscriptions()->first();

        if ($customer->wasChanged() && $subscription) {
            dispatch(
                (new SubscriptionUpdated($subscription))->postTo($customer->merchant_id)
            )->afterCommit();
        }
    }
}
