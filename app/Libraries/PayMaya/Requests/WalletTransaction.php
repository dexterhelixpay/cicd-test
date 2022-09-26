<?php

namespace App\Libraries\PayMaya\Requests;

use Illuminate\Support\Facades\Validator;

class WalletTransaction extends Request
{
    /**
     * Set the total amount of the transaction.
     *
     * @param  float  $value
     * @param  string  $currency
     * @return $this
     */
    public function setTotalAmount(float $value, string $currency = 'PHP')
    {
        $this->data['totalAmount'] = [
            'value' => $value,
            'currency' => $currency,
        ];

        return $this;
    }

    /**
     * Set the request reference number for the transaction.
     *
     * @param  string  $rrn
     * @return $this
     */
    public function setRrn(string $rrn)
    {
        $this->data['requestReferenceNumber'] = $rrn;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        Validator::validate($this->data, [
            'totalAmount.value' => 'required|numeric',
            'totalAmount.currency' => 'required|string',
            'requestReferenceNumber' => 'required|string',
        ]);
    }
}
