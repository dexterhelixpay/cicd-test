<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

class Date
{
    /**
     * Constant representing valid date formats.
     *
     * @var string[]
     */
    const DATE_FORMATS = [
        'Y-m-d',
        'd/m/Y',
        'd/m/y',
        'm/d/y',
        'n/d/Y',
        'n/d/y',
        'n/j/Y',
        'n/t/Y',
    ];

    /**
     * Check if the given date string follows the given format.
     *
     * @param  string  $value
     * @param  string|null  $format
     * @return bool
     */
    public static function isValid(string $value, ?string $format = null)
    {
        return (bool) collect($format ?? self::DATE_FORMATS)
            ->first(function ($format) use ($value) {
                try {
                    $date = Carbon::createFromFormat($format, $value);

                    return $date && $value === $date->format($format);
                } catch (Throwable) {
                    return false;
                }
            });
    }

    /**
     * Convert the given date string to a Carbon instance.
     *
     * @param  string  $date
     * @return \Illuminate\Support\Carbon|null
     */
    public static function toCarbon(string $date)
    {
        $validFormat = collect(self::DATE_FORMATS)
            ->first(function ($format) use ($date) {
                return self::isValid($date, $format);
            });

        if (!$validFormat) {
            return null;
        }

        return Carbon::createFromFormat($validFormat, $date);
    }
}
