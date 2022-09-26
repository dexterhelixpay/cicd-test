<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class Slug implements Rule
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
        if (!preg_match('/^[A-Z0-9-]+$/i', $value)) {
            return false;
        }

        if (!preg_match('/^[A-Z0-9]/i', $value)) {
            return false;
        }

        if (!preg_match('/[A-Z0-9]$/i', $value)) {
            return false;
        }

        if (preg_match('/\-\-/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.slug');
    }
}
