<?php

namespace App\Providers;

use App\Auth\Guards\ApiKeyGuard;
use App\Auth\Guards\NullGuard;
use App\Auth\NullUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::extend('null', function () {
            return new NullGuard;
        });

        Auth::provider('null', function () {
            return new NullUserProvider;
        });

        Auth::viaRequest('api', new ApiKeyGuard);

        Passport::personalAccessTokensExpireIn(now()->addYear());
    }
}
