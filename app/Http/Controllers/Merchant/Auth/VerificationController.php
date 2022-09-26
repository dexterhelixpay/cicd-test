<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Http\Controllers\Controller;
use App\Models\MerchantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Vinkla\Hashids\Facades\Hashids;

class VerificationController extends Controller
{
    /**
     * Check if the given verification token is correct.
     *
     * @param  \Illuminate\Http\Request
     * @param  string  $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request, $token)
    {
        if (!$user = $this->findUserFromToken($token)) {
            response()->json([], 404);
        }

        return response()->json([
            'for_update' => !is_null(optional($user)->new_email),
        ]);
    }

    /**
     * Verify the given user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => MerchantUser::getPasswordRules(true),
        ]);

        if (!$merchantUser = $this->findUserFromToken($request->input('token'))) {
            return response()->json([], 404);
        }

        if (!$merchantUser->new_email) {
            $merchantUser
                ->fill(['password' => bcrypt($request->input('password'))])
                ->markEmailAsVerified();

            $lastRequest = $merchantUser->lastRequest()->first();

            if ($lastRequest) {
                $lastRequest->touch();
            }

            return response()->json();
        }

        if (!Hash::check($request->input('password'), $merchantUser->password)) {
            return response()->json([], 404);
        }

        $merchantUser
            ->fill(['email' => $merchantUser->new_email])
            ->forceFill(['new_email' => null])
            ->markEmailAsVerified();

        return response()->json();
    }

    /**
     * Find the user from the given verification token.
     *
     * @param  string  $token
     * @return \App\Models\MerchantUser|null
     */
    protected function findUserFromToken($token)
    {
        $tokenParts = explode('.', $token);

        if (count($tokenParts) !== 2) {
            return null;
        }

        $key = Hashids::connection('merchant_user')->decode($tokenParts[0]);

        if (count($key) !== 1) {
            return null;
        }

        if (!$merchantUser = MerchantUser::find($key[0])) {
            return null;
        }

        if (!Hash::check($tokenParts[1], $merchantUser->verification_code ?? '')) {
            return null;
        }

        return $merchantUser;
    }
}
