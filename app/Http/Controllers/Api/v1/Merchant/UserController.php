<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\UnauthorizedException;

class UserController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant');
        $this->middleware('permission:CP: Merchants - View|MC: Users')->only('index','show');
        $this->middleware('permission:CP: Merchants - Edit|MC: Users')->except('index','show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $users = QueryBuilder::for($merchant->users()->getQuery(), 'users')
            ->when($request->input('filter.user_email'), function ($query, $email) {
                $query->where(function ($query) use ($email) {
                    $query->where('email', $email)->orWhere('new_email', $email);
                });
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $user = $merchant->users()
                ->make(Arr::except($request->input('data.attributes'), ['role', 'password']));

            $user->save();

            $this->validateRoleChangeRequest(
                $request,
                $role = $request->input('data.relationships.roles.data.attributes.name')
            );

            $user
                ->syncRoles($role)
                ->syncPermissions(
                    $request->input('data.relationships.permissions.data.*.attributes.name')
                );

            if ($role === 'Owner') {
                $merchant->users()
                    ->role('Owner')
                    ->whereKeyNot($user->getKey())
                    ->get()
                    ->each(function ($user) {
                        $user
                            ->syncRoles('Admin')
                            ->syncPermissions($user->roles()->first()->getAllPermissions());
                    });
            }

            return new CreatedResource($user->fresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $user)
    {
        $this->authorizeRequest($request, $merchant);

        $user = QueryBuilder::for($merchant->users()->getQuery())
            ->whereKey($user)
            ->apply()
            ->first();

        if (!$user) {
            throw (new ModelNotFoundException)->setModel(MerchantUser::class);
        }

        return new Resource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantUser  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, MerchantUser $user)
    {
        $this->authorizeRequest($request, $merchant);

        if ($request->filled('data.attributes.is_verification_email_resent')) {
            return $this->resendVerificationEmail($user);
        }

        if ($request->hasOnly('is_enabled', 'data.attributes')) {
            return $this->updateAccountStatus($request, $user);
        }

        $this->validateRequest($request, $merchant, $user);

        return DB::transaction(function () use ($request, $user, $merchant) {
            $user->fill(Arr::except($request->input('data.attributes'), 'password'));

            if ($request->filled('data.attributes.password')) {
                $user->password = bcrypt($request->input('data.attributes.password'));
            }

            $user->save();

            $this->validateRoleChangeRequest(
                $request,
                $role = $request->input('data.relationships.roles.data.attributes.name'),
                $user
            );

            $user
                ->syncRoles($role)
                ->syncPermissions(
                    $request->input('data.relationships.permissions.data.*.attributes.name')
                );

            if ($role === 'Owner') {
                $merchant->users()
                    ->role('Owner')
                    ->whereKeyNot($user->getKey())
                    ->get()
                    ->each(function ($user) {
                        $user
                            ->syncRoles('Admin')
                            ->syncPermissions($user->roles()->first()->getAllPermissions());
                    });
            }

            return new Resource($user->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantUser  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, MerchantUser $user)
    {
        $this->authorizeRequest($request, $merchant);

        if (!$user->delete()) {
            throw (new ModelNotFoundException)->setModel(MerchantUser::class);
        }

        return new Resource($user);
    }

    /**
     * Update account status
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MerchantUser  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateAccountStatus(Request $request, MerchantUser $user)
    {
        return DB::transaction(function () use ($request, $user) {
            $user->update($request->input('data.attributes', []));

            return new Resource($user->fresh());
        });
    }

    /**
     * Change password of the merchant user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\int  $merchantUser
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request, Merchant $merchant, int $merchantUser)
    {
        $this->authorizeRequest($request, $merchant);

        $merchantUser = $merchant->users()->findOrFail($merchantUser);

        $data = Arr::except($request->input('data.attributes'), 'old_password');

        $request->validate([
            'data.attributes.old_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($merchantUser, $request) {
                    if (!Hash::check($value, $merchantUser->password)) {
                        $fail('Old password is incorrect.');
                    }
                }
            ],

            'data.attributes.new_password' => MerchantUser::getPasswordRules(true),
        ]);

        $merchantUser->hasUsedPassword($request->input('data.attributes.new_password'), true);

        return DB::transaction(function () use ($data, $merchantUser) {
            $merchantUser->forceFill([
                'password' => bcrypt($data['new_password'])
            ])->update();

            return $this->okResponse();
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantUser|null  $user
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest($request, $merchant, $user = null)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Validate the given role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $role
     * @param  \App\Models\MerchantUser|null  $user
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function validateRoleChangeRequest($request, $role, $user = null)
    {
        $requestUser = $request->user();

        if (
            !$user
            && $role === 'Owner'
            && $requestUser instanceof MerchantUser
            && !$requestUser->hasRole('Owner')
        ) {
            throw new UnauthorizedException;
        }

        if (
            $user
            && $role !== 'Owner'
            && $requestUser instanceof MerchantUser
            && $requestUser->hasRole('Owner')
            && $user->is($requestUser)
        ) {
            throw new BadRequestException('There must be at least one owner account.');
        }
    }

    /**
     * Resend the user's verification email.
     *
     * @param  \App\Models\MerchantUser  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function resendVerificationEmail($user)
    {
        return DB::transaction(function () use ($user) {
            $code = $user->generateVerificationCode();
            $user->sendEmailVerificationNotification($code);

            return new Resource($user->fresh());
        });
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantUser|null  $user
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request, $merchant, $user = null)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
        ]);

        if ($user) {
            return $request->validate([
                'data.attributes.name' => 'sometimes|string|max:180',
                'data.attributes.username' => [
                    'sometimes',
                    'string',
                    'max:180',
                    Rule::unique('merchant_users', 'username')
                        ->ignoreModel($user)
                        ->withoutTrashed(),
                ],
                'data.attributes.password' => [
                    'sometimes',
                    'nullable',
                    MerchantUser::getPasswordRules(),
                ],
                'data.attributes.email' => [
                    'sometimes',
                    'email',
                    Rule::unique('merchant_users', 'email')
                        ->where('merchant_id', $merchant->getKey())
                        ->ignoreModel($user)
                        ->withoutTrashed(),
                ],
                'data.attributes.mobile_number' => 'sometimes|nullable|mobile_number',
                'data.attributes.is_enabled' => 'sometimes|boolean',
            ]);
        }

        $request->validate([
            'data.attributes.name' => 'required|string|max:180',
            'data.attributes.username' => [
                'required',
                'string',
                'max:180',
                Rule::unique('merchant_users', 'username')
                    ->withoutTrashed(),
            ],
            'data.attributes.email' => [
                'required',
                'email',
                Rule::unique('merchant_users', 'email')
                    ->where('merchant_id', $merchant->getKey())
                    ->withoutTrashed(),
            ],
            'data.attributes.mobile_number' => [
                'sometimes',
                'nullable',
                'mobile_number',
            ],
        ]);
    }


}
