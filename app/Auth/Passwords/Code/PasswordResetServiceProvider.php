<?php

namespace App\Auth\Passwords\Code;

use Illuminate\Auth\Passwords\PasswordResetServiceProvider as ServiceProvider;

class PasswordResetServiceProvider extends ServiceProvider
{
    /**
     * Register the password broker instance.
     *
     * @return void
     */
    protected function registerPasswordBroker()
    {
        $this->app->singleton('auth.password.code', function ($app) {
            return new PasswordBrokerManager($app);
        });

        $this->app->bind('auth.password.code.broker', function ($app) {
            return $app->make('auth.password.code')->broker();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['auth.password.code', 'auth.password.code.broker'];
    }
}
