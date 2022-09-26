<?php

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\AddUserToSecurityGroup;
use App\Resolvers\IpAddressResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user');
    }

    /**
     * Attempt to authenticate a new session using the two factor authentication code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($code = $this->validRecoveryCode($request)) {
            $user->replaceRecoveryCode($code);
        } elseif (!$this->hasValidCode($request)) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ]);
        }

        $user->forceFill([
            'two_factor_verified_at' => $user->two_factor_verified_at ?: now(),
        ])->saveQuietly();

        dispatch(new AddUserToSecurityGroup($user, IpAddressResolver::resolve()));

        return response()->json([
            'access_token' => $user->createToken('Control Panel Token')->accessToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Get the valid recovery code if one exists on the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function validRecoveryCode(Request $request)
    {
        if (!$recoveryCode = $request->input('recovery_code')) {
            return;
        }

        return collect($request->user()->recoveryCodes())
            ->first(function ($code) use ($recoveryCode) {
                return hash_equals($recoveryCode, $code) ? $code : null;
            });
    }

    /**
     * Determine if the request has a valid two factor code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function hasValidCode(Request $request)
    {
        return $request->input('code') && app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($request->user()->two_factor_secret), $request->input('code')
        );
    }
}
