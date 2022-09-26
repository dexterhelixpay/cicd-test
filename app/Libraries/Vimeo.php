<?php

namespace App\Libraries;

use App\Libraries\Vimeo\Video;

class Vimeo
{
    /**
     * Create a new Vimeo instance.
     *
     * @param  string  $apiUrl
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $accessToken
     */
    public function __construct(
        public string $apiUrl,
        public string $clientId,
        public string $clientSecret,
        public string $accessToken
    ) {
        //
    }

    /**
     * Create a new Vimeo video instance.
     *
     * @return \App\Libraries\Vimeo\Video
     */
    public function videos()
    {
        return new Video(
            $this->apiUrl, $this->clientId, $this->clientSecret, $this->accessToken
        );
    }
}
