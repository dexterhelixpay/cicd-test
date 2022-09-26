<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Facades\CodePassword;
use App\Http\Controllers\Controller;
use App\Models\MerchantUser;
use Illuminate\Http\Request;

class PasswordController extends Controller
{
    /**
     * Send a reset code to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        if (MerchantUser::where($request->only('email'))->exists()) {
            CodePassword::broker('merchant_users')->sendResetLink($request->only('email'));
        }

        return response()->json();
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => MerchantUser::getPasswordRules(true),
        ]);

        $status = CodePassword::broker('merchant_users')
            ->reset(
                $request->only('token', 'email', 'password'),
                function ($merchant, $password) {
                    $merchant->password = bcrypt($password);
                    $merchant->update();
                }
            );

        $statusCode = $status === CodePassword::PASSWORD_RESET ? 200 : 400;

        return response()->json(['message' => trans($status)], $statusCode);
    }
}
