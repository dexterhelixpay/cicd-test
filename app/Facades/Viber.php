<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \GuzzleHttp\Client send()
 * @method static \GuzzleHttp\Client setup()
 * @method static \GuzzleHttp\Client remove()
 *
 */
class Viber extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'viber';
    }
}
