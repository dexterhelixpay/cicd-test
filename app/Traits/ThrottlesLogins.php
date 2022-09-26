<?php

namespace App\Traits;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ThrottlesLogins
{
    /**
     * Determine if the user has too many failed login attempts.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function hasTooManyLoginAttempts(Request $request)
    {
        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request), $this->maxAttempts()
        );
    }

    /**
     * Increment the login attempts for the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function incrementLoginAttempts(Request $request)
    {
        $errorMessage = $this->app == 'Storefront'
            ? trans('auth.invalid_code')
            : trans('auth.failed');

        \Log::channel('login')->error($errorMessage, [
            'context' => [
                'class' => ValidationException::class,
                'message' => $errorMessage,
                'app' => $this->app,
                'username' => $request->input('username')
                    ?: $request->input('email')
                    ?: $request->input('mobile_number'),
                'user-agent' => $request->userAgent(),
                'ip_address' => trim(
                    shell_exec("dig +short myip.opendns.com @resolver1.opendns.com")
                ) ?? $request->ip()
            ]
        ]);

        $this->limiter()->hit(
            $this->throttleKey($request), $this->decayMinutes() * 60
        );
    }

    /**
     * Redirect the user after determining they are locked out.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendLockoutResponse(Request $request)
    {
        $username = $request->input('email') ?: $request->input('mobile_number');

        $seconds = $this->limiter()->availableIn(
            $this->throttleKey($request)
        );

        $errorMessage = Lang::get('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => ceil($seconds / 60),
        ]);

        \Log::channel('login')->error($errorMessage, [
            'context' => [
                'class' => ValidationException::class,
                'message' => $errorMessage,
                'app' => $this->app,
                'username' => $request->input('username')
                    ?: $request->input('email')
                    ?: $request->input('mobile_number'),
                'user-agent' => $request->userAgent(),
                'ip_address' => trim(
                    shell_exec("dig +short myip.opendns.com @resolver1.opendns.com")
                ) ?? $request->ip()
            ]
        ]);

        throw ValidationException::withMessages([
            $username => [Lang::get('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ])->status(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Clear the login locks for the given user credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function clearLoginAttempts(Request $request)
    {
        $this->limiter()->clear($this->throttleKey($request));
    }

    /**
     * Fire an event when a lockout occurs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function fireLockoutEvent(Request $request)
    {
        event(new Lockout($request));
    }

    /**
     * Get the throttle key for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function throttleKey(Request $request)
    {
        $username = $request->input('email') ?: $request->input('mobile_number');

        return Str::lower($username).'|'.$request->ip();
    }

    /**
     * Get the rate limiter instance.
     *
     * @return \Illuminate\Cache\RateLimiter
     */
    protected function limiter()
    {
        return app(RateLimiter::class);
    }

    /**
     * Get the maximum number of attempts to allow.
     *
     * @return int
     */
    public function maxAttempts()
    {
        return property_exists($this, 'maxAttempts') ? $this->maxAttempts : 3;
    }

    /**
     * Get the number of minutes to throttle for.
     *
     * @return int
     */
    public function decayMinutes()
    {
        return property_exists($this, 'decayMinutes') ? $this->decayMinutes : 5;
    }
}
