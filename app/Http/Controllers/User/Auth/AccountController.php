<?php

namespace App\Http\Controllers\User\Auth;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
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
     * Get the current user's info.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInfo(Request $request)
    {
        $user = QueryBuilder::for(User::class)
            ->whereKey($request->user()->getKey())
            ->apply()
            ->first();

        return response()->json($user);
    }

    /**
     * Change password of the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user) throw new BadRequestException('User not found');

        $request->validate([
            'data.attributes.old_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($user, $request) {
                    if (!Hash::check($value, $user->password)) {
                        $fail('Old password is incorrect.');
                    }
                }
            ],

            'data.attributes.new_password' => User::getPasswordRules(true),
        ]);

        $user->hasUsedPassword($request->input('data.attributes.new_password'), true);

        return DB::transaction(function () use ($request, $user) {
            $data = Arr::except($request->input('data.attributes'), 'old_password');

            $user->forceFill([
                'password' => bcrypt($data['new_password']),
                'is_required_to_change_password' => false
            ])->update();

            return $this->okResponse();
        });
    }
}
