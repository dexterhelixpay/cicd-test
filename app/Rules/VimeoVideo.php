<?php

namespace App\Rules;

use App\Facades\Vimeo;
use Illuminate\Contracts\Validation\InvokableRule;

class VimeoVideo implements InvokableRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function __invoke($attribute, $value, $fail)
    {
        if (!$value) {
            return $fail('The selected :attribute is invalid.');
        }

        $response = Vimeo::videos()->find($value);

        if ($response->failed()) {
            return $fail('The selected :attribute is invalid.');
        }

        if ($response->json('upload.status') !== 'complete') {
            return $fail('The selected :attribute is not ready.');
        }
    }
}
