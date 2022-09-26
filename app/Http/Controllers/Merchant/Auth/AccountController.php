<?php

namespace App\Http\Controllers\Merchant\Auth;

use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:merchant');
    }

    /**
     * Get the current user's info.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserInfo(Request $request)
    {
        $user = QueryBuilder::for(MerchantUser::class)
            ->whereKey($request->user()->getKey())
            ->apply()
            ->first();

        return response()->json($user);
    }
}
