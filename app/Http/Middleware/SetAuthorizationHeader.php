<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetAuthorizationHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('get') && !$request->expectsJson() && $request->filled('_token')) {
            $request->headers->set('Authorization', "Bearer {$request->query('_token')}");
        }

        return $next($request);
    }
}
