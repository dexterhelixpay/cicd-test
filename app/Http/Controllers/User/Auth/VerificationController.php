<?php

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
        return response()
            ->json([], $this->findUserFromToken($token) ? 200 : 404);
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
            'password' => User::getPasswordRules(true),
        ]);

        if (!$user = $this->findUserFromToken($request->input('token'))) {
            return response()->json([], 404);
        }

        $user
            ->fill(['password' => bcrypt($request->input('password'))])
            ->forceFill(['verification_code' => null])
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

        $key = Hashids::connection('user')->decode($tokenParts[0]);

        if (count($key) !== 1) {
            return null;
        }

        if (!$user = User::find($key[0])) {
            return null;
        }

        if (!Hash::check($tokenParts[1], $user->verification_code ?? '')) {
            return null;
        }

        return $user;
    }
}
