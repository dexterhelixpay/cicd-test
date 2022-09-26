<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class EmailAttachment implements Rule
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
        $total_size = array_reduce($value, function ( $sum, $item ) {
            if (is_string($item['attributes']['pdf'])) return $sum;

            $sum += filesize($item['attributes']['pdf']);
            return $sum;
        });

        return $total_size < 26214400 ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.email_attachment');
    }
}
