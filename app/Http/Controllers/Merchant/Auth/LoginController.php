<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Traits\ThrottlesLogins;
use App\Exceptions\MultipleSessionException;
use App\Http\Controllers\Controller;
use App\Models\LastHttpRequest;
use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
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
        $this->middleware('auth:merchant')->only('logout');
        $this->maxAttempts = setting('ConsoleMaxAttempts', 3);
        $this->decayMinutes =  setting('ConsoleLockoutPeriod', 5);
        $this->app = 'Console';
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
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = MerchantUser::firstWhere('username', $request->input('username'));

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if (!$user) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'username' => [trans('auth.failed')],
            ]);
        }

        if (!$user->is_enabled || !optional($user->merchant)->is_enabled) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'username' => [trans('auth.disabled')],
            ]);
        }

        $user->isPasswordExpired(true);

        $response = Http::asForm()->post(route('passport.token'), [
            'grant_type' => 'password',
            'client_id' => oauth_client('merchant_users')->id,
            'client_secret' => oauth_client('merchant_users')->secret,
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'scope' => '',
        ]);

        if ($response->failed()) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                'username' => [trans('auth.failed')],
            ]);
        }

        $lastRequest = $user->lastRequest()->first();

        // $ip = trim(shell_exec("dig +short myip.opendns.com @resolver1.opendns.com"))
        //     ?? $request->ip();

        // if (
        //     $lastRequest
        //     && $lastRequest->isDifferentSession($ip, $request->userAgent())
        // ) {
        //     if (!$request->has('forceLogoutActiveSession')) {
        //         throw new MultipleSessionException;
        //     }

        //     $user->tokens()->get()->each(function ($token) use($lastRequest) {
        //         if ($token->id == data_get($lastRequest['token'], 'id')) {
        //             $token->revoke();
        //         }
        //     });

        //     DB::table('oauth_refresh_tokens')
        //         ->where('access_token_id', data_get($lastRequest['token'], 'id'))
        //         ->delete();

        //     $lastRequest->forceFill(['is_revoke' => true])->update();
        // }

        if ($lastRequest) {
            $lastRequest->touch();
        }

        $this->clearLoginAttempts($request);

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
