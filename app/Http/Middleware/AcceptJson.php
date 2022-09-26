<?php

namespace App\Http\Middleware;

use App\Exceptions\BadRequestException;
use Closure;
use Illuminate\Http\Request;

class AcceptJson
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
        if (!$request->acceptsJson()) {
            throw new BadRequestException('The API only accepts JSON requests.');
        }

        return $next($request);
    }
}
