<?php

namespace App\Libraries\PayMaya\Requests;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Customer extends Request
{
    /**
     * Set the billing address.
     *
     * @param  string  $countryCode
     * @param  string|null  $line1
     * @param  string|null  $line2
     * @param  string|null  $city
     * @param  string|null  $state
     * @param  string|null  $zipCode
     * @return $this
     */
    public function setBillingAddress(
        $countryCode,
        $line1 = null,
        $line2 = null,
        $city = null,
        $state = null,
        $zipCode = null,
    ) {
        $this->data['billingAddress'] = collect(get_defined_vars())
            ->filter()
            ->toArray();

        return $this;
    }

    /**
     * Set the contact info.
     *
     * @param  string|null  $email
     * @param  string|null  $phone
     * @return $this
     */
    public function setContact($email, $phone)
    {
        $this->data['contact'] = get_defined_vars();

        return $this;
    }

    /**
     * Set the customer's info.
     *
     * @param  string  $firstName
     * @param  string  $lastName
     * @param  string|null  $customerSince
     * @return $this
     */
    public function setInfo(
        $firstName,
        $lastName,
        $customerSince = null
    ) {
        $this->data = array_merge($this->data, get_defined_vars());

        return $this;
    }

    /**
     * Set the shipping address.
     *
     * @param  string  $countryCode
     * @param  string|null  $firstName
     * @param  string|null  $lastName
     * @param  string|null  $email
     * @param  string|null  $phone
     * @param  string|null  $line1
     * @param  string|null  $line2
     * @param  string|null  $city
     * @param  string|null  $state
     * @param  string|null  $zipCode
     * @return $this
     */
    public function setShippingAddress(
        $countryCode,
        $firstName = null,
        $lastName = null,
        $email = null,
        $phone = null,
        $line1 = null,
        $line2 = null,
        $city = null,
        $state = null,
        $zipCode = null,
    ) {
        $this->data['shippingAddress'] = collect(get_defined_vars())
            ->filter()
            ->toArray();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        Validator::validate($this->data, [
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'customerSince' => 'sometimes|nullable|date_format:Y-m-d',
            'contact.email' => 'required_without:contact.phone|email',
            'contact.phone' => 'required_without:contact.email',
            'billingAddress.countryCode' => [
                'required',
                Rule::exists('countries', 'code'),
            ],
            'shippingAddress.countryCode' => [
                'required',
                Rule::exists('countries', 'code'),
            ],
        ]);
    }
}
