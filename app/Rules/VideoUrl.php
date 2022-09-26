<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class VideoUrl implements Rule
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
        $parsedUrl = parse_url($value);

        switch ($parsedUrl['host'] ?? null) {
            case 'www.youtube.com':
                parse_str($parsedUrl['query'] ?? '', $query);

                return preg_match('/^\/watch$/', $parsedUrl['path'] ?? '')
                    && isset($query['v']);

            case 'vimeo.com':
                return preg_match('/^\/[0-9]+$/', $parsedUrl['path'] ?? '');

            default:
                return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.video_url');
    }
}
