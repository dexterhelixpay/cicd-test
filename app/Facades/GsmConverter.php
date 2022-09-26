<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string cleanUpUtf8String(string $string, bool $translit, ?string $replaceChars = null)
 */
class GsmConverter extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'gsm.converter';
    }
}
