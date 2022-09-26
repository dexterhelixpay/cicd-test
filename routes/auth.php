<?php

use App\Http\Controllers\User\Auth\TwoFactorController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Passport\Passport;
use Laravel\Passport\RouteRegistrar;

Route::name('user.')
    ->prefix('user')
    ->namespace('User')
    ->middleware('user')
    ->group(function () {
        Route::name('auth.')
            ->prefix('auth')
            ->namespace('Auth')
            ->group(function (Router $auth) {
                $auth->post('login', 'LoginController@login')->name('login');
                $auth->get('logout', 'LoginController@logout')->name('logout');

                Route::prefix('user')->group(function () {
                    Route::get('/', 'AccountController@getUserInfo')->name('user');
                    Route::post('change_password', 'AccountController@changePassword')
                        ->name('user.change_password');

                    Route::post('2fc', [TwoFactorController::class, 'store']);

                    Route::middleware('auth:user')->group(function () {
                        Route::get('2fa', [TwoFactorQrCodeController::class, 'show']);
                        Route::post('2fa', [TwoFactorAuthenticationController::class, 'store']);

                        Route::get('recovery_codes', [RecoveryCodeController::class, 'index']);
                        Route::post('recovery_codes', [RecoveryCodeController::class, 'store']);
                    });
                });

                $auth->get('verify/{token}', 'VerificationController@check')->name('verify.check');
                $auth->post('verify', 'VerificationController@verify')->name('verify');

                Route::name('password.')
                    ->prefix('password')
                    ->group(function (Router $password) {
                        $password->post('code', 'PasswordController@sendResetCode')->name('code');
                        $password->post('reset', 'PasswordController@reset')->name('reset');
                    });
            });
    });

Route::name('merchant.')
    ->prefix('merchant')
    ->namespace('Merchant')
    ->middleware('merchant')
    ->group(function () {
        Route::name('auth.')
            ->prefix('auth')
            ->namespace('Auth')
            ->group(function (Router $auth) {
                $auth->post('login', 'LoginController@login')->name('login');
                $auth->get('logout', 'LoginController@logout')->name('logout');

                $auth->get('user', 'AccountController@getUserInfo')->name('user');

                $auth->get('verify/{token}', 'VerificationController@check')->name('verify.check');
                $auth->post('verify', 'VerificationController@verify')->name('verify');

                Route::name('password.')
                    ->prefix('password')
                    ->group(function (Router $password) {
                        $password->post('code', 'PasswordController@sendResetCode')->name('code');
                        $password->post('reset', 'PasswordController@reset')->name('reset');
                    });
            });
    });

Route::name('customer.')
    ->prefix('customer')
    ->namespace('Customer')
    ->group(function () {
        Route::name('auth.')
            ->prefix('auth')
            ->namespace('Auth')
            ->group(function (Router $auth) {
                $auth->post('login', 'LoginController@login')->name('login');
                $auth->post('logout', 'LoginController@logout')->name('logout');

                $auth->get('user', 'AccountController@getUserInfo')->name('user');

                $auth->post('otp/check', 'VerificationController@check')->name('otp.check');
                $auth->post('otp/send', 'VerificationController@send')->name('otp.send');
            });
    });

Passport::routes(function (RouteRegistrar $registrar) {
    $registrar->forAccessTokens();
});
