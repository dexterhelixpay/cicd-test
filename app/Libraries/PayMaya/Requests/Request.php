<?php

namespace App\Libraries\PayMaya\Requests;

abstract class Request
{
    /**
     * The request data.
     *
     * @var array
     */
    protected $data = [];

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
     * Set the payment facilitator metadata.
     *
     * @param  string|null  $smi
     * @param  string|null  $smn
     * @param  string  $mci
     * @param  string  $mpc
     * @param  string  $mco
     * @return $this
     */
    public function withPaymentFacilitator(
        string|null $smi = null,
        string $smn,
        string $mci,
        string $mpc,
        string $mco
    ) {
        data_set($this->data, 'metadata.pf', get_defined_vars());

        return $this;
    }
}
