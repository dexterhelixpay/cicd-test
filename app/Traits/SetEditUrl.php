<?php

namespace App\Traits;

use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Str;
trait SetEditUrl
{

    /**
     * The edit url
     *
     * @var string
     */
    public $editUrl;

    /**
     * The edit url
     *
     * @var string
     */
    public $editProductUrl;

    /**
     * The type
     *
     * @var string
     */
    public $type;


    /**
     * Set urls
     *
     */
    public function setEditUrl(
        $orderId,
        $subscriptionId,
        $customerId,
        $isConsoleBooking = false,
        $isPayment = false,
        $success = true,
        $fromWhere = 'email'
    ) {
        $editUrl = !$isConsoleBooking
            ? config('bukopay.url.edit')
            : config('bukopay.url.subscription_checkout');


        $this->editUrl = "https://{$this->merchant->subdomain}.{$editUrl}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($subscriptionId),
            'ord' => Hashids::connection('order')->encode($orderId),
            'cust' => Hashids::connection('customer')->encode($customerId),
            'isPayment' => $isPayment,
            'success' => $success,
            'type' => $this->type,
            'isConsoleBooking' => $isConsoleBooking,
            'isFrom' . Str::ucfirst($fromWhere) => 1
        ]);

        return $this->editUrl;
    }

    /**
     * Set email type
     *
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set edit product urls
     *
     */
    public function setEditProductUrl($orderId, $subscriptionId, $customerId,  $isConsoleBooking = false, $isFromEmail = true)
    {
        $profileUrl = !$isConsoleBooking
            ? config('bukopay.url.profile')
            : config('bukopay.url.subscription_checkout');

        $this->editProductUrl = "https://{$this->merchant->subdomain}.{$profileUrl}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($subscriptionId),
            'ord' => Hashids::connection('order')->encode($orderId),
            'cust' => Hashids::connection('customer')->encode($customerId),
            'isFromEmail' => $isFromEmail,
            'type' => $this->type,
            'isConsoleBooking' => $isConsoleBooking

        ]);
    }

}
