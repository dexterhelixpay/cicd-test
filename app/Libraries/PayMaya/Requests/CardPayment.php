<?php

namespace App\Libraries\PayMaya\Requests;

use Illuminate\Support\Facades\Validator;

class CardPayment extends Request
{
    /**
     * Set the total amount of the transaction.
     *
     * @param  float  $amount
     * @param  string  $currency
     * @return $this
     */
    public function setTotalAmount(float $amount, string $currency = 'PHP')
    {
        $this->data['totalAmount'] = [
            'amount' => $amount,
            'currency' => $currency,
        ];

        return $this;
    }

    /**
     * Set the URL for success redirection.
     *
     * @param  string  $url
     * @return $this
     */
    public function redirectOnSuccess(string $url)
    {
        data_set($this->data, 'redirectUrl.success', $url);

        return $this;
    }

    /**
     * Set the URL for failure redirection.
     *
     * @param  string  $url
     * @return $this
     */
    public function redirectOnFailure(string $url)
    {
        data_set($this->data, 'redirectUrl.failure', $url);
        data_set($this->data, 'redirectUrl.cancel', $url);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        Validator::validate($this->data, [
            'totalAmount.amount' => 'required|numeric',
            'totalAmount.currency' => 'required|string',
        ]);
    }
}
