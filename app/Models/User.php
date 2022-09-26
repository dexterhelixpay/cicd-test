<?php

namespace App\Models;

use App\Notifications\UserPasswordReset;
use App\Notifications\UserVerification;
use App\Traits\HasApiTokens;
use App\Traits\HasPasswordHistory;
use App\Traits\VerifiesEmails;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends RecordableUser
{
    use HasApiTokens,
        HasFactory,
        HasPasswordHistory,
        HasRoles,
        Notifiable,
        SoftDeletes,
        TwoFactorAuthenticatable,
        VerifiesEmails;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',

        'is_required_to_change_password',
        'is_enabled',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'verification_code',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'password_updated_at' => 'datetime',
        'is_required_to_change_password' => 'boolean',
        'is_enabled' => 'boolean'
    ];

    /**
     * Find the user instance for the given email.
     *
     * @param  string  $email
     * @return self
     */
    public function findForPassport($email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Get the guard used for roles/permissions.
     *
     * @return string
     */
    public function guardName()
    {
        return 'user';
    }

    /**
     * Get customer's last request.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function lastRequest(): MorphOne
    {
        return $this->morphOne(LastHttpRequest::class, 'user')
            ->where('is_revoke', false);
    }

    /**
     * Send a password reset notification to the user.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new UserPasswordReset($token));
    }

    /**
     * Send the email verification notification.
     *
     * @param  string|null  $code
     * @param  bool  $forUpdate
     * @return void
     */
    public function sendEmailVerificationNotification($code = null, $forUpdate = false)
    {
        $this->notify(new UserVerification($code, $forUpdate));
    }
}
