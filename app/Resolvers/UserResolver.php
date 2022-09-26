<?php

declare(strict_types=1);

namespace App\Resolvers;

use Altek\Accountant\Contracts\Identifiable;
use Altek\Accountant\Contracts\UserResolver as UserResolverContract;
use App\Models\User;
use Lcobucci\JWT\Parser;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class UserResolver implements UserResolverContract
{
    /**
     * Resolve the User.
     *
     * @return Identifiable
     */
    public static function resolve(): ?Identifiable
    {
        static $user = null;

        if ($user) {
            return $user;
        }

        foreach (config('accountant.user.guards') as $guard) {
            if ($foundUser = auth()->guard($guard)->user()) {
                if ($hashedUser = self::getUserFromHash($foundUser)) {
                    return $user = $hashedUser;
                }

                return $user = $foundUser;
            }
        }

        return null;
    }

    /**
     * Get the user from the token's hash claim.
     *
     * @param  \Illuminate\Foundation\Auth\User  $user
     * @return \App\Models\User|null
     */
    protected static function getUserFromHash($user)
    {
        if (!$accessToken = request()->bearerToken()) {
            return null;
        }

        try {
            $token = app(Parser::class)->parse($accessToken);

            if (!$token->claims()->has('ush')) {
                return null;
            }

            $userId = Hashids::connection('user')->decode($token->claims()->get('ush'));

            if (count($userId)) {
                [$userId] = $userId;

                return $user instanceof User && $user->getKey() == $userId
                    ? $user
                    : User::find($userId);
            }
        } catch (Throwable) {
            return null;
        }
    }
}
