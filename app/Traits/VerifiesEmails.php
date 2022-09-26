<?php

namespace App\Traits;

use App\Models\MerchantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait VerifiesEmails
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootVerifiesEmails()
    {
        static::created(function (Model $model) {
            if ($model->password) {
                return;
            }

            $code = $model->generateVerificationCode();
            $model->sendEmailVerificationNotification($code);
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged('email') && !$model->wasChanged('email_verified_at')) {
                if (request()->isFromUser() && !$model instanceof User) {
                    return $model->fresh()
                        ->forceFill(['new_email' => null])
                        ->markEmailAsVerified();
                }

                if ($model->hasVerifiedEmail() && $model instanceof User) {
                    $model->forceFill([
                        'email_verified_at' => null,
                    ])->saveQuietly();
                }

                $originalEmail = $model->getOriginal('email');

                $code = $model->generateVerificationCode();
                $model->sendEmailVerificationNotification($code, $model->hasVerifiedEmail());

                if ($model->hasVerifiedEmail()) {
                    $model->forceFill([
                        'email' => $originalEmail,
                        'new_email' => $model->email,
                    ])->saveQuietly();
                }
            }
        });
    }

    /**
     * Scope a query to only include users with verified emails.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $verified
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmailVerified($query, $verified = true)
    {
        return $query->{$verified ? 'whereNotNull' : 'whereNull'}('email_verified_at');
    }

    /**
     * Generate a new verification code.
     *
     * @return string
     */
    public function generateVerificationCode()
    {
        $code = Str::random();

        $this->forceFill(['verification_code' => bcrypt($code)])->saveQuietly();

        return $code;
    }

    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified()
    {
        return $this
            ->forceFill([
                'verification_code' => null,
                'email_verified_at' => $this->freshTimestamp(),
            ])
            ->save();
    }
}
