<?php

namespace App\Auth\Guards;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;

class NullGuard implements StatefulGuard
{
    /**
     * {@inheritdoc}
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function once(array $credentials = [])
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasUser()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function login(Authenticatable $user, $remember = false)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function loginUsingId($id, $remember = false)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function onceUsingId($id)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function viaRemember()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function logout()
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function check()
    {
        return true;
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function user()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function id()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $credentials = [])
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setUser(Authenticatable $user)
    {
        //
    }
}
