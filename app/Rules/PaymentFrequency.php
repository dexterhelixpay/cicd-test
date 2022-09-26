<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PaymentFrequency implements Rule
{
    /**
     * The valid payment frequencies.
     *
     * @var array
     */
    protected $frequencies = [
        'single',
        'weekly',
        'semimonthly',
        'monthly',
        'bimonthly',
        'quarterly',
        'semiannual',
        'annually',
    ];

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
        if (in_array($value, $this->frequencies)) {
            return true;
        }

        return is_numeric($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validations.payment_frequency', [
            'options' => join(', ', $this->frequencies),
        ]);
    }
}
