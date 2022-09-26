<?php

namespace App\Traits;

use App\Exceptions\PasswordAlreadyUsedException;
use App\Exceptions\PasswordExpiredException;
use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

trait HasPasswordHistory
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasPasswordHistory()
    {
        static::created(function ($model) {
            $model->logPasswordUpdate();
        });

        static::updated(function ($model) {
            $model->logPasswordUpdate();
        });
    }

    /**
     * Get the rules for the password.
     *
     * @param  bool  $required
     * @return string
     */
    public static function getPasswordRules($required = false)
    {
        $settings = Setting::whereIn('key', [
            'PasswordMinLength',
            'PasswordRequireLetters',
            'PasswordRequireMixedCase',
            'PasswordRequireNumbers',
            'PasswordRequireSymbols',
        ])->get();

        $rule = Password::min(
            optional($settings->firstWhere('key', 'PasswordMinLength'))->value ?? 8
        );

        if (optional($settings->firstWhere('key', 'PasswordRequireLetters'))->value ?? true) {
            $rule->letters();
        }

        if (optional($settings->firstWhere('key', 'PasswordRequireMixedCase'))->value ?? true) {
            $rule->mixedCase();
        }

        if (optional($settings->firstWhere('key', 'PasswordRequireNumbers'))->value ?? true) {
            $rule->numbers();
        }

        if (optional($settings->firstWhere('key', 'PasswordRequireSymbols'))->value ?? true) {
            $rule->symbols();
        }

        return $required ? $rule->required() : $rule;
    }

    /**
     * Get the password's history.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function passwordHistories()
    {
        return $this->morphMany(PasswordHistory::class, 'user');
    }

    /**
     * Delete old password histories.
     *
     * @return self
     */
    public function deleteOldPasswordHistory()
    {
        $maxHistoryCount = setting('PasswordMaxHistoryCount', 4);

        if ($this->passwordHistories()->count() > $maxHistoryCount) {
            $this->passwordHistories()
                ->orderBy('id', 'desc')
                ->get()
                ->skip($maxHistoryCount)
                ->each(function (PasswordHistory $history) {
                    $history->delete();
                });
        }

        return $this;
    }

    /**
     * Check if the user has used the given password before.
     *
     * @param  string  $password
     * @param  bool  $throwException
     * @return bool|void
     *
     * @throws \App\Exceptions\PasswordAlreadyUsedException
     */
    public function hasUsedPassword($password, $throwException = false)
    {
        $hasUsedPassword = $this->passwordHistories()
            ->get()
            ->toBase()
            ->contains(function (PasswordHistory $history) use ($password) {
                return Hash::check($password, $history->password);
            });

        if (!$throwException) {
            return $hasUsedPassword;
        }

        if ($hasUsedPassword) {
            throw new PasswordAlreadyUsedException;
        }
    }

    /**
     * Check if the user's password is expired.
     *
     * @param  bool  $throwException
     * @return bool
     *
     * @throws \App\Exceptions\PasswordExpiredException
     */
    public function isPasswordExpired($throwException = false)
    {
        $isExpired = Carbon::parse($this->password_updated_at)
            ->addDays(setting('PasswordMaxAge', 90))
            ->startOfDay()
            ->lte(now());

        if (!$throwException) {
            return $isExpired;
        }

        if ($isExpired) {
            throw new PasswordExpiredException;
        }
    }

    /**
     * Log the password update to history.
     *
     * @param  bool  $force
     * @return self
     */
    public function logPasswordUpdate($force = false)
    {
        if (
            $this->password
            && ($force || $this->wasRecentlyCreated || $this->wasChanged('password'))
        ) {
            $this->fresh()->setAttribute('password_updated_at', now())->save();
            $this->passwordHistories()->create($this->only('password'));
            $this->deleteOldPasswordHistory();
        }

        return $this;
    }

    /**
     * Scope a query to only include payable orders.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpiredPassword($query)
    {
        return $query->whereDate(
            'password_updated_at',
            '<=',
            now()->subDays(setting('PasswordMaxAge', 90))->startOfDay()
        );
    }

    /**
     * Scope a query to only include payable orders.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpiringPassword($query)
    {
        $days = max(
            setting('PasswordMaxAge', 90) - setting('PasswordDaysBeforeExpirationReminder', 3),
            0
        );

        return $query->whereDate('password_updated_at', now()->subDays($days)->startOfDay());
    }
}
