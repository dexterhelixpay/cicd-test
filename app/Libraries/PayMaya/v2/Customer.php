<?php

namespace App\Libraries\PayMaya\v2;

use App\Libraries\PayMaya\Requests\Customer as CustomerRequest;

class Customer extends Api
{
    /**
     * Find the given customer by ID.
     *
     * @param  string  $id
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function find(string $id, string $secretKey)
    {
        return $this->client($secretKey)->get("customers/{$id}");
    }

    /**
     * Create a customer from the given request.
     *
     * @param  \App\Libraries\PayMaya\Requests\Customer  $request
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function create(CustomerRequest $request, string $secretKey)
    {
        $request->validate();

        return $this->client($secretKey)->post('customers', $request->data());
    }

    /**
     * Update the customer with the given ID.
     *
     * @param  string  $id
     * @param  \App\Libraries\PayMaya\Requests\Customer  $request
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function update(string $id, CustomerRequest $request, string $secretKey)
    {
        $request->validate();

        return $this->client($secretKey)->put("customers/{$id}", $request->data());
    }
}
