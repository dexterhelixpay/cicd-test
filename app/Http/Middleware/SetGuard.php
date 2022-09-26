<?php

namespace App\Http\Middleware;

use Closure;

class SetGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $guards = array_keys(config('auth.guards'));

        $guard = in_array($guard, $guards)
            ? $guard
            : config('auth.defaults.guard');

        auth()->shouldUse($guard);

        return $next($request);
    }
}
