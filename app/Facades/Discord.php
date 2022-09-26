<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Libraries\Discord\Guild guilds(string $version = 'v10')
 * @method static \App\Libraries\Discord\OAuth oAuth(string $version = 'v10')
 * @method static \App\Libraries\Discord\User users(string $version = 'v10')
 *
 */
class Discord extends Facade
{
    /**
     * Constant representing guild channel type category.
     *
     * @var int
     */
    const GUILD_CATEGORY = 4;

    /**
     * Constant representing guild channel type text.
     *
     * @var int
     */
    const GUILD_TEXT = 0;

    /**
     * Constant representing guild channel type text.
     *
     * @var int
     */
    const GUILD_VOICE = 1;

    /**
     * Constant representing default permission.
     *
     * @var int
     */
    const DEFAULT_PERMISSION = 448894721600;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'discord';
    }
}
