<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Traits\ThrottlesSms;
use App\Models\LoginOtpLog;
use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Traits\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
class VerificationController extends Controller
{
    use ThrottlesSms, ThrottlesLogins;

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
        $this->maxAttempts = setting('CheckoutStorefrontMaxAttempts', 3);
        $this->decayMinutes =  setting('CheckoutStorefrontLockOutPeriod', 5);
        $this->app = 'Storefront';
    }

    /**
     * Check if the verification code is valid.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function check(Request $request)
    {
        $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->whereNull('deleted_at'),
            ],
            'code' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            $customer = Customer::findOrFail($request->input('customer_id'));

            $lastSubscription = $customer->subscriptions()->latest('id')->first();

            $isValid = $this->verifyCode($customer, $request->input('code'));

            if ($this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);

                return $this->sendLockoutResponse($request);
            }

            if (!$isValid) {
                $this->incrementLoginAttempts($request);

                return $this->badRequestResponse(trans('auth.invalid_code'));
            }

            if ($customer->new_mobile_number) {
                $customer->forceFill([
                    'new_mobile_number' => null,
                ]);
            }

            if ($customer->new_country_id) {
                $customer->forceFill([
                    'new_country_id' => null,
                ]);
            }

            $customer->update();

            $lastRequest = $customer->lastRequest()->first();

            if ($lastRequest) {
                $lastRequest->touch();
            }

            $this->clearLoginAttempts($request);

            return response()->json([
                'customer' => $customer->load('membership','membershipProducts','blastVouchers'),
                'lastSubscription' => $lastSubscription,
                'token' => $customer->createToken('Store Front Token')->accessToken
            ]);
        });
    }

    /**
     * Send the customer's verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer|null  $customer
     * @return \Illuminate\Http\Response
     */
    public function send(Request $request, Customer $customer)
    {
        $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->whereNull('deleted_at'),
            ],
            'new_mobile_number' => [
                'sometimes',
            ],
        ]);

        return DB::transaction(function () use ($request) {
            $customer = Customer::findOrFail($request->input('customer_id'));

            if ($request->has('new_mobile_number')) {
                $customer->forceFill([
                        'new_mobile_number' => $request->input('new_mobile_number')
                    ]);
            }

            if ($request->has('new_country')) {
                $customer->forceFill([
                    'new_country_id' => $request->input('new_country')
                ]);
            }

            $customer->update();

            $this->throttleSms(function () use ($customer, $request) {
                $customer->sendVerificationCode($request->has('new_mobile_number') || $request->has('new_country'));
            });

            LoginOtpLog::create([
                'customer_id' => $customer?->id,
                'merchant_id' =>  $customer?->merchant?->id,
                'is_resend' => data_get($request, 'is_resend', false),
                'mobile_number' => $customer->mobile_number
            ]);
        });
    }

    /**
     * Check if the customer exists and the verification code, if given, is valid.
     *
     * @param  \App\Models\Customer|null  $customer
     * @param  string|null  $verificationCode
     *
     * @return \App\Models\Customer|null
     */
    protected function verifyCode(Customer $customer, string $verificationCode = null)
    {
        return Hash::check($verificationCode, $customer->verification_code)
            ? $customer
            : null;
    }
}
