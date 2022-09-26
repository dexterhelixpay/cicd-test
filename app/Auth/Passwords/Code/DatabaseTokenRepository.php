<?php

namespace App\Auth\Passwords\Code;

use Illuminate\Auth\Passwords\DatabaseTokenRepository as Repository;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Str;

class DatabaseTokenRepository extends Repository
{
    /**
     * {@inheritdoc}
     */
    public function exists(CanResetPassword $user, $token)
    {
        $record = (array) $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->first();

        return $record
            && !$this->tokenExpired($record['created_at'])
            && $this->hasher->check($token, $record['token']);
    }

    /**
     * {@inheritdoc}
     */
    public function createNewToken()
    {
        return mb_strtoupper(Str::random(6));
    }
}
