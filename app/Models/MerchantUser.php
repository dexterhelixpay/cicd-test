<?php

namespace App\Models;

use App\Notifications\MerchantPasswordReset;
use App\Notifications\MerchantUserVerification;
use App\Traits\HasApiTokens;
use App\Traits\HasPasswordHistory;
use App\Traits\VerifiesEmails;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class MerchantUser extends RecordableUser
{
    use HasApiTokens, HasPasswordHistory, HasRoles, Notifiable, SoftDeletes, VerifiesEmails;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username',
        'email',
        'country_id',
        'mobile_number',
        'formatted_mobile_number',
        'password',

        'name',

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
        'is_enabled' => 'boolean',
        'email_verified_at' => 'timestamp',
        'password_updated_at' => 'datetime',
    ];

    /**
     * Find the user instance for the given username.
     *
     * @param  string  $username
     * @return self
     */
    public function findForPassport($username)
    {
        return $this
            ->where('username', $username)
            ->where('is_enabled', true)
            ->emailVerified()
            ->first();
    }

    /**
     * Get the guard used for roles/permissions.
     *
     * @return string
     */
    public function guardName()
    {
        return 'merchant';
    }

    /**
     * Route notifications for the SMS channel.
     *
     * @param  \Illuminate\Notifications\Notification|null  $notification
     * @return string
     */
    public function routeNotificationForSms($notification = null)
    {
        return $this->mobile_number;
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
        $this->notify(new MerchantUserVerification($code, $forUpdate));
    }

    /**
     * Send a password reset notification to the user.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MerchantPasswordReset($token));
    }

    /**
    * Get customer's last request
    *
    * @return \Illuminate\Database\Eloquent\Relations\MorphOne
    */
   public function lastRequest(): MorphOne
   {
       return $this->morphOne(LastHttpRequest::class, 'user')
        ->where('token->name', '!=', 'Merchant Users Personal Access Client')
        ->where('is_revoke', false);
   }

    /**
    * Get customer's last request
    *
    * @return \Illuminate\Database\Eloquent\Relations\MorphOne
    */
    public function adminLastRequest(): MorphOne
    {
        return $this->morphOne(LastHttpRequest::class, 'user')
         ->where('token->name', 'Merchant Users Personal Access Client')
         ->where('is_revoke', false);
    }

    /**
     * Get the country.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class)->withDefault([
            'id' => 175,
            'name' => 'Philippines',
            'code' => 'PH',
            'flag' => '🇵🇭',
            'dial_code' => '+63',
        ]);
    }

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
