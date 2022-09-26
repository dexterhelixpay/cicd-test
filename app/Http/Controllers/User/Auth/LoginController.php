<?php

namespace App\Http\Controllers\User\Auth;

use App\Exceptions\MultipleSessionException;
use App\Http\Controllers\Controller;
use App\Jobs\AddUserToSecurityGroup;
use App\Models\User;
use App\Resolvers\IpAddressResolver;
use App\Traits\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

class LoginController extends Controller
{
    use ThrottlesLogins;

    /**
     * The maximum login attempts
     *
     * @var int
     */
    public $maxAttempts;

     /**
     * The maximum login attempts
     *
     * @var int
     */
    public $decayMinutes;

     /**
     * The application
     *
     * @var string
     */
    public $app;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user')->only('logout');
        $this->maxAttempts = setting('CPMaxAttempts', 3);
        $this->decayMinutes =  setting('CPLockOutPeriod', 5);
        $this->app = 'Control Panel';
    }

    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email',$request->input('email'))->first();

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if (!$user) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (!$user->is_enabled) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'username' => [trans('auth.disabled')],
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'email' => [trans('auth.unverified')],
            ]);
        }

        $user->isPasswordExpired(true);

        if (app()->isProduction()) {
            Passport::tokensExpireIn(
                $user->hasEnabledTwoFactorAuthentication()
                    ? now()->addMinutes(15)
                    : now()->addYear()
            );
        }

        $response = Http::asForm()->post(route('passport.token'), [
            'grant_type' => 'password',
            'client_id' => oauth_client('users')->id,
            'client_secret' => oauth_client('users')->secret,
            'username' => $request->input('email'),
            'password' => $request->input('password'),
            'scope' => '',
        ]);

        if ($response->failed()) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $lastRequest = $user->lastRequest()->first();

        if ($lastRequest) {
            if (!$request->has('forceLogoutActiveSession')) {
                throw new MultipleSessionException;
            }

            $user->tokens()->get()->each(function ($token) use($lastRequest) {
                if ($token->id == data_get($lastRequest['token'], 'id')) {
                    $token->revoke();
                }
            });

            DB::table('oauth_refresh_tokens')
                ->where('access_token_id', data_get($lastRequest['token'], 'id'))
                ->delete();

            $lastRequest->forceFill(['is_revoke' => true])->update();
        }

        if ($lastRequest) {
            $lastRequest->touch();
        }

        $this->clearLoginAttempts($request);

        dispatch(new AddUserToSecurityGroup($user, IpAddressResolver::resolve()));

        return $response->toPsrResponse();
    }

    /**
     * Handle a logout request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        $user->tokens()->get()->each(function ($token) {
            DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $token->getKey())
                ->delete();

            $token->revoke();
        });

        $lastRequest = $user->lastRequest()->first();

        if ($lastRequest) {
            $lastRequest->forceFill(['is_revoke' => true])->update();
        }

        return response()->json();
    }
}
