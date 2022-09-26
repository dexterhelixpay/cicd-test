<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
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
        $this->middleware('auth:user');
        $this->middleware('permission:CP: User Management - View')->only('index','show');
        $this->middleware('permission:CP: User Management - Add')->only('store');
        $this->middleware('permission:CP: User Management - Edit')->only('update');
        $this->middleware('permission:CP: User Management - Delete')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = QueryBuilder::for(User::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validateRequest($request);
        $this->authorizeRequest($request, 'CP: User Management - Add');

        return DB::transaction(function () use ($request) {
            $password = $request->input('data.attributes.password')
                ? bcrypt($request->input('data.attributes.password'))
                : null;
            $role = $request->input('data.relationships.roles.data.attributes.name');
            $permissions = data_get($request->input('data.relationships.permissions.data'), '*.attributes.name');

            ($user = User::make())
                ->fill(Arr::except($request->input('data.attributes'), 'password'))
                ->setAttribute('password', $password)
                ->save();

            $this->validateRoleChangeRequest($role);
            $user->syncRoles($role);

            $this->validatePermissionsChangeRequest($permissions, $user);
            $user->syncPermissions($permissions);

            if ($password) {
                $user->markEmailAsVerified();
            }

            return new CreatedResource($user->fresh());
        });

    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $user)
    {
        $this->authorizeRequest($request, 'CP: User Management - View');

        $user = QueryBuilder::for(User::class)
            ->whereKey($user)
            ->apply()
            ->first();

        if (!$user) {
            throw (new ModelNotFoundException)->setModel(User::class);
        }

        return new Resource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeRequest($request, 'CP: User Management - Edit');

        if ($request->filled('data.attributes.is_verification_email_resent')) {
            return $this->resendVerificationEmail($user);
        }

        if ($request->filled('data.attributes.is_two_factor_disabled')) {
            return $this->disableTwoFactor($request, $user);
        }

        if ($request->hasOnly('is_enabled', 'data.attributes')) {
            return $this->updateAccountStatus($request, $user);
        }

        $this->validateRequest($request, $user->id);

        return DB::transaction(function () use ($request, $user) {
            $user->fill(Arr::except($request->input('data.attributes'), 'password'));
            $role = data_get($request->input('data.relationships.roles.data'), 'attributes.name' );
            $permissions = data_get($request->input('data.relationships.permissions.data'), '*.attributes.name');

            if ($request->filled('data.attributes.password')) {
                $user->password = bcrypt($request->input('data.attributes.password'));

                if (
                    $request->isFromUser()
                    && $request->user()->id != $user->id
                ) {
                    $user->is_required_to_change_password = true;
                }
            }

            if ( optional($user->roles->first())->name !== $role) {
                $this->validateRoleChangeRequest($role);
                $user->syncRoles($role);
            }

            $this->validatePermissionsChangeRequest($permissions, $user);
            $user->syncPermissions($permissions);

            $user->update();

            if ($user->password && !$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            return new Resource($user->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorizeRequest($request, 'CP: User Management - Delete');

        if (!$user->delete()) {
            throw (new ModelNotFoundException)->setModel(User::class);
        }

        return new Resource($user);
    }

    /**
     * Disable the user's two-factor authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function disableTwoFactor(Request $request, $user)
    {
        return DB::transaction(function () use ($request, $user) {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_verified_at' => null,
            ])->save();

            return new Resource($user->fresh());
        });
    }

    /**
     * Update account status
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateAccountStatus(Request $request, User $user)
    {
        return DB::transaction(function () use ($request, $user) {
            if(auth()->user()->roles->first()->name !== "Super Admin") {
                throw new UnauthorizedException;
            }

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
    public function changePassword(Request $request, User $user)
    {
        $this->authorizeRequest($request);

        $data = Arr::except($request->input('data.attributes'), 'old_password');

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

        return DB::transaction(function () use ($data, $user) {
            $user->forceFill(['password' => bcrypt($data['new_password'])])->update();

            return $this->okResponse();
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $requiredPermission
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest($request, $requiredPermission = null)
    {
        if (
            !$request->isFromUser()
            || (
                !empty($requiredPermission)
                && !in_array(
                    $requiredPermission,
                    User::findOrFail(auth()->id())->getPermissionNames()->toArray()
                )
            )
        ) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Validate role change request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $role
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function validateRoleChangeRequest($role)
    {
        if (
            Auth::user()->roles->first()->name != 'Super Admin'
            && $role == 'Super Admin'
        ) {
            abort(403, 'Your are not allowed to assign Super Admin role.');
        }
    }

    /**
     * Validate role change request.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function validatePermissionsChangeRequest($permissions, $user)
    {
        if (
            !auth()->user()->hasAnyPermission([
                'CP: User Management - Add',
                'CP: User Management - Edit',
                'CP: User Management - View',
                'CP: User Management - Delete',
            ])
        ) {
            abort(403, 'Your are not allowed to assign permissions.');
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
     * @param  \App\Models\User  $user
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request, $user = null)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.name' => 'sometimes|string|max:180',
            'data.attributes.password' => [
                'sometimes',
                'nullable',
                User::getPasswordRules(),
            ],
            'data.attributes.email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($user)
                    ->withoutTrashed(),
            ]
        ]);
    }
}
