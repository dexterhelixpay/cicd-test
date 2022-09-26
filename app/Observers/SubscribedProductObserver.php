<?php

namespace App\Observers;

use App\Models\Post;
use Illuminate\Support\Arr;

class SubscribedProductObserver
{
    /**
     * Handle the subscribed product "creating" event.
     *
     * @param  \App\Models\SubscribedProduct  $subscribedProduct
     * @return void
     */
    public function creating($subscribedProduct)
    {
        $this->setPaymentScheduleDay($subscribedProduct);
        $this->setProductState($subscribedProduct);
    }

    /**
     * Handle the subscribed product "created" event.
     *
     * @param  \App\Models\SubscribedProduct  $subscribedProduct
     * @return void
     */
    public function created($subscribedProduct)
    {
        $this->clearPostCache($subscribedProduct);
    }

    /**
     * Handle the subscribed product "updated" event.
     *
     * @param  \App\Models\SubscribedProduct  $subscribedProduct
     * @return void
     */
    public function updated($subscribedProduct)
    {
        $this->clearPostCache($subscribedProduct);
    }

    /**
     * Set the day or day of week of the payment schedule if missing.
     *
     * @param  \App\Models\SubscribedProduct  $subscribedProduct
     * @return void
     */
    protected function clearPostCache($subscribedProduct)
    {
        $wasChanged = $subscribedProduct->wasChanged('is_membership')
            || ($subscribedProduct->wasRecentlyCreated && $subscribedProduct->is_membership);

        if ($wasChanged) {
            Post::flushQueryCache();
        }
    }

    /**
     * Set the day or day of week of the payment schedule if missing.
     *
     * @param  \App\Models\SubscribedProduct  $subscribedProduct
     * @return void
     */
    protected function setPaymentScheduleDay($subscribedProduct)
    {
        $schedule = $subscribedProduct->payment_schedule;

        if (is_null($schedule)) {
            $schedule = ['frequency' => 'monthly'];
        }

        if (in_array($schedule['frequency'], ['single', 'monthly', 'quarterly'])) {
            unset($schedule['day_of_week']);

            if (!Arr::has($schedule, 'day')) {
                $schedule['day'] = $subscribedProduct->subscription->created_at->day;
            }
        }

        if (in_array($schedule['frequency'], ['weekly', 'semimonthly'])) {
            unset($schedule['day']);

            if (!Arr::has($schedule, 'day_of_week')) {
                $schedule['day_of_week'] = $subscribedProduct->subscription->created_at->dayOfWeek;
            }
        }

        if (in_array($schedule['frequency'], ['annually'])) {
            unset($schedule['day_of_week']);

            if (!Arr::has($schedule, 'month')) {
                $schedule['month'] = $subscribedProduct->subscription->created_at->month;
            }

            if (!Arr::has($schedule, 'day')) {
                $schedule['day'] = $subscribedProduct->subscription->created_at->day;
            }
        }

        $subscribedProduct->payment_schedule = $schedule;
    }

    /**
     * Set the product's state.
     *
     * @param  \App\Models\SubscribedProduct  $subscribedProduct
     * @return self
     */
    public function setProductState($subscribedProduct)
    {
        if ($product = $subscribedProduct->product()->first()) {
            $productDetails = $subscribedProduct->shopify_product_info
                ? ['title', 'description', 'are_multiple_orders_allowed', 'is_membership']
                : ['title', 'description', 'are_multiple_orders_allowed', 'is_shippable', 'is_membership'];

            $subscribedProduct->fill($product->only($productDetails));
        }

        if ($product && !$subscribedProduct->variant()->exists()) {
            $subscribedProduct->setVariantFromPaymentSchedule();
        }

        if ($subscribedProduct->variant && !$subscribedProduct->option_values) {
            $subscribedProduct->option_values = $subscribedProduct->variant->mapOptionValues();
        }

        if ($subscribedProduct->variant && !isset($subscribedProduct->price)) {
            $subscribedProduct->price = $subscribedProduct->variant->price;

            $subscribedProduct->setTotalPrice();
        }
    }
}
