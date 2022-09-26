<?php

namespace App\Libraries\PayMaya\v2;

use App\Libraries\PayMaya\Requests\CardLink;
use App\Libraries\PayMaya\Requests\CardPayment;

class CustomerCard extends Api
{
    /**
     * Find the given card of the customer.
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function find(string $customerId, string $cardId, string $secretKey)
    {
        return $this->client($secretKey)->get("{$customerId}/cards/{$cardId}");
    }

    /**
     * Get the linked cards of the given customer.
     *
     * @param  string  $customerId
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function get(string $customerId, string $secretKey)
    {
        return $this->client($secretKey)->get("{$customerId}/cards");
    }

    /**
     * Link the given card to the customer.
     *
     * @param  string  $customerId
     * @param  \App\Libraries\PayMaya\Requests\CardLink  $request
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function link(string $customerId, CardLink $request, string $secretKey)
    {
        $request->validate();

        return $this->client($secretKey)->post("{$customerId}/cards", $request->data());
    }

    /**
     * Pay using the given customer's card.
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @param  \App\Libraries\PayMaya\Requests\CardPayment  $request
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function pay(string $customerId, string $cardId, CardPayment $request, string $secretKey)
    {
        $request->validate();

        return $this->client($secretKey)
            ->post("{$customerId}/cards/{$cardId}/payments", $request->data());
    }
}
