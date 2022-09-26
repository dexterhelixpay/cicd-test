<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class MobileNumber implements Rule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];

    /**
     * The country id
     *
     * @var integer|null
     */
    protected $countryId;

    /**
     * Constant representing a Philippines.
     *
     * @var int
     */
    const COUNTRY_PHILIPPINES = 175;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$this->countryId || $this->countryId == self::COUNTRY_PHILIPPINES) {
            return preg_match('/^(?:\+?63|0)?[0-9]{10}$/', $value);
        }

        return preg_match('/^[1-9][0-9]*$/', $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.mobile_number');
    }

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->countryId = data_get($data, 'data.attributes.country_id');

        return $this;
    }
}
