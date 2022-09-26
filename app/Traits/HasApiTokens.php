<?php

namespace App\Traits;

use App\Passport\PersonalAccessTokenFactory;
use Illuminate\Container\Container;
use Laravel\Passport\HasApiTokens as BaseTrait;

trait HasApiTokens
{
    use BaseTrait;

    /**
     * Create a new personal access token for the user.
     *
     * @param  string  $name
     * @param  array  $scopes
     * @return \Laravel\Passport\PersonalAccessTokenResult
     */
    public function createToken($name, array $scopes = [])
    {
        return Container::getInstance()->make(PersonalAccessTokenFactory::class)->make(
            $this, $name, $scopes
        );
    }
}
