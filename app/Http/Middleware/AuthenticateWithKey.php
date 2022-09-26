<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\UnauthorizedException;

class AuthenticateWithKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (
            !$request->hasHeader('X-Secret-Key')
            && !$request->hasHeader('X-Notification-Key')
        ) {
            throw new UnauthorizedException;
        }

        $hashedKey = setting('CachedNotificationKey', '');
        $key = $request->header('X-Secret-Key') ?? $request->header('X-Notification-Key');

        if (!Hash::check($key, $hashedKey)) {
            throw new UnauthorizedException;
        }

        return $next($request);
    }
}
