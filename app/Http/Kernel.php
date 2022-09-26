<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\AllowShopifyXFrame::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'authorize',
            'throttle:api',
            'logLastRequest',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'merchant' => [
            'guard:merchant',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'user' => [
            'guard:user',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'customer' => [
            'guard:customer',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'logLastRequest' => \App\Http\Middleware\LogLastRequest::class,
        'permission' => \App\Http\Middleware\CheckPermission::class,
        'authorize' => \App\Http\Middleware\SetAuthorizationHeader::class,
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.client' => \App\Http\Middleware\AuthenticateOrCheckCredentials::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.key' => \App\Http\Middleware\AuthenticateWithKey::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'client' => \Laravel\Passport\Http\Middleware\CheckClientCredentials::class,
        'guard' => \App\Http\Middleware\SetGuard::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'json' => \App\Http\Middleware\AcceptJson::class,
        'lock' => \App\Http\Middleware\LockAndWaitRequest::class,
        'logged' => \App\Http\Middleware\LogRequest::class,
        'na.null' => \App\Http\Middleware\ConvertNotApplicableToNull::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'bindings'   => Illuminate\Routing\Middleware\SubstituteBindings::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var string[]
     */
    protected $middlewarePriority = [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequests::class,
        \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Auth\Middleware\Authorize::class,
        \App\Http\Middleware\LockAndWaitRequest::class,
        \App\Http\Middleware\LogRequest::class,
    ];
}
