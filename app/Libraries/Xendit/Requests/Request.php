<?php

namespace App\Libraries\Xendit\Requests;

abstract class Request
{
    /**
     * The request data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * The request headers.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Validate the request.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    abstract public function validate();

    /**
     * Get the request data.
     *
     * @return array
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Get the request headers.
     *
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Make the transaction for the given sub-account.
     *
     * @param  string  $userId
     * @return $this
     */
    public function forUser($userId)
    {
        $this->headers['for-user-id'] = $userId;

        return $this;
    }
}
