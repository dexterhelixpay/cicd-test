<?php

namespace App\Libraries\PayMaya\Requests;

use Illuminate\Support\Facades\Validator;

class CardLink extends Request
{
    /**
     * The request data.
     *
     * @var array
     */
    protected $data = [
        'isDefault' => false,
    ];

    /**
     * Set the payment token.
     *
     * @param  string  $url
     * @return $this
     */
    public function setToken(string $token)
    {
        $this->data['paymentTokenId'] = $token;

        return $this;
    }

    /**
     * Set the "default" flag.
     *
     * @param  bool  $isDefault
     * @return $this
     */
    public function seAsDefault(bool $isDefault = true)
    {
        $this->data['isDefault'] = $isDefault;

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
            'paymentTokenId' => 'required|string',
            'isDefault' => 'sometimes|boolean',
        ]);
    }
}
