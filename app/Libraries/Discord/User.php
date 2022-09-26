<?php

namespace App\Libraries\Discord;

use App\Facades\Discord;

class User extends Api
{
    /**
     * Get authorize user.
     *
     * @param  string  $token
     * @return \Illuminate\Http\Client\Response
     */
    public function getAuthUser(string $token)
    {
        return $this->userClient($token)->get("@me")->json();
    }
}
