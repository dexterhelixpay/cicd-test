<?php

namespace App\Auth\Guards;

use App\Models\ApiKey;
use App\Models\MerchantUser;
use Illuminate\Http\Request;

class ApiKeyGuard
{
    /**
     * Get the authenticated user from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Foundation\Auth\User|null
     */
    public function __invoke(Request $request)
    {
        $request->headers->remove('X-Api-Request');

        if (!$token = $request->bearerToken()) {
            return null;
        }

        if (!$decodedToken = base64_decode($token, true)) {
            return null;
        }

        $tokenParts = explode('.', $decodedToken);

        if (count($tokenParts) !== 2) {
            return null;
        }

        [$prefix, $secret] = $tokenParts;

        if (!$key = ApiKey::where('prefix', $prefix)->where('is_enabled', true)->first()) {
            return null;
        }

        if ($secret !== $key->secret) {
            return null;
        }

        $createdBy = $key->createdBy()->first();

        $request->headers->set('X-Api-Request', 1);

        return $createdBy instanceof MerchantUser
            ? $createdBy
            : $key->merchant->owner;
    }
}
