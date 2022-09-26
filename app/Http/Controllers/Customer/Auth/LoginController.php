<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoginOtpLog;
use App\Models\Merchant;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ThrottlesSms;
use Illuminate\Validation\Rule;

class LoginController extends Controller
{
    use ThrottlesSms;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:customer')->only('logout');
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
            'mobile_number' => [
                Rule::requiredIf(!$request->filled('email'))
            ],
            'merchant_id' => [
                'required',
                Rule::exists('merchants', 'id')
                    ->whereNull('deleted_at'),
            ]
        ]);

        return DB::transaction(function () use ($request) {
            $merchant = Merchant::findOrFail($request->input('merchant_id'));

            $attributes = [
                'merchant_id' => $request->input('merchant_id'),
                'mobile_number' => $request->input('mobile_number'),
                'country_id' => $request->input('country_id')
            ];

            if ($request->filled('email')) {
                $attributes['email'] = $request->input('email');
            }

            if ($request->has('is_members_login')) {
                $username = $request->filled('email') ? 'email' : 'mobile number';

                $customer = Customer::query()
                    ->where('merchant_id', $request->input('merchant_id'))
                    ->when($request->input('email'), function ($query, $email) {
                        $query->where('email', $email);
                    }, function ($query) use ($request) {
                        $query->where('mobile_number', $request->input('mobile_number'));
                    })
                    ->first();

                if (!$customer) {
                    throw new BadRequestException(
                        "Couldn't find a membership with that {$username}"
                    );
                }

                $activeMemberships = (new CustomerService)->getActiveSubscriptions(
                    $customer, function ($query) {
                        $query->whereRelation('products', 'is_membership', true);
                    }
                );

                if ($activeMemberships->isEmpty()) {
                    throw new BadRequestException(
                        "Couldn't find a membership with that {$username}"
                    );
                }
            } else {
                $customer = $merchant->customers()->firstOrCreate($attributes);
            }

            $this->throttleSms(function () use ($customer, $request) {
                $customer->sendVerificationCode(type:'login', isMobileNumber: $request->filled('mobile_number'));
            });

            LoginOtpLog::create([
                'customer_id' => $customer?->id,
                'merchant_id' =>  $request->input('merchant_id'),
                'is_resend' => data_get($request, 'is_resend', false),
                'mobile_number' => data_get($request, 'mobile_number'),
                'email' => data_get($request, 'email')
            ]);


            $lastRequest = $customer->lastRequest()->first();

            if ($lastRequest) {
                $lastRequest->touch();
            }

            return response()->json([
                'customer' => $customer->load('membership','membershipProducts'),
            ]);
        });
    }

    /**
     * Handle a logout request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $accessToken = $request->user()->token();

        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $accessToken->getKey())
            ->delete();

        $accessToken->revoke();

        return response()->json();
    }
}
