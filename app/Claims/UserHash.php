<?php

namespace App\Claims;

use CorBosman\Passport\AccessToken;
use Vinkla\Hashids\Facades\Hashids;

class UserHash
{
    public function handle(AccessToken $token, $next)
    {
        if (request()->isFromUser()) {
            $user = request()->userOrClient();

            $token->addClaim('ush', Hashids::connection('user')->encode($user->getKey()));
            $token->addClaim('usn', $user->name);
        }

        return $next($token);
    }
}
