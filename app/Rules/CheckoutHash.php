<?php

namespace App\Rules;

use App\Models\Checkout;
use Illuminate\Contracts\Validation\Rule;
use Vinkla\Hashids\Facades\Hashids;

class CheckoutHash implements Rule
{
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
        $decodedValue = Hashids::connection('checkout')->decode($value);

        if (count($decodedValue) === 0) {
            return false;
        }

        return Checkout::whereKey($decodedValue[0])->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.checkout_hash');
    }
}
