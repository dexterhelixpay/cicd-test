<?php

namespace App\Libraries;

use App\Libraries\Discord\User;
use App\Libraries\Discord\Guild;
use App\Libraries\Discord\OAuth;

class Discord
{
    /**
     * The Discord Redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The Discord Client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The Discord Client Secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * The Discord Discord Bot Token.
     *
     * @var string
     */
    protected $botToken;

    /**
     * Create a new Discord instance.
     *
     * @param  string  $apiUrl
     * @return void
     */
    public function __construct(
        $apiUrl,
        $redirectUrl,
        $clientId,
        $clientSecret,
        $botToken
    ) {
        $this->apiUrl = $apiUrl;
        $this->redirectUrl = $redirectUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->botToken = $botToken;
    }

    /**
     * Create a new Discord guild instance.
     *
     * @param  string  $version
     * @return \App\Libraries\Discord\Guild
     */
    public function guilds(string $version = 'v10')
    {
        return new Guild(
            join('/', [$this->apiUrl, $version, 'guilds']),
            $this->redirectUrl,
            $this->clientId,
            $this->clientSecret,
            $this->botToken
        );
    }

    /**
     * Create a new oAuth instance.
     *
     * @param  string  $version
     * @return \App\Libraries\Discord\OAuth
     */
    public function oAuth(string $version = 'v10')
    {
        return new OAuth(
            join('/', [$this->apiUrl, $version, 'oauth2']),
            $this->redirectUrl,
            $this->clientId,
            $this->clientSecret,
        );
    }

    /**
     * Create a new users instance.
     *
     * @param  string  $version
     * @return \App\Libraries\Discord\User
     */
    public function users(string $version = 'v10')
    {
        return new User(
            join('/', [$this->apiUrl, $version, 'users']),
            $this->redirectUrl
        );
    }
}
