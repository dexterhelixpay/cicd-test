<?php

namespace App\Libraries\PayMaya\Requests;

use Illuminate\Support\Facades\Validator;

class WalletLink extends Request
{
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
            'redirectUrl.success' => 'required|url',
            'redirectUrl.failure' => 'required|url',
            'redirectUrl.cancel' => 'required|url',
        ]);
    }
}
