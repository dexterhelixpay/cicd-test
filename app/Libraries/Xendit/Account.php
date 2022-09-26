<?php

namespace App\Libraries\Xendit;

use App\Libraries\Xendit;

class Account extends Xendit
{
    /**
     * Constant representing a live account.
     *
     * @var string
     */
    const STATUS_LIVE = 'LIVE';

    /**
     * Constant representing a registered account.
     *
     * @var string
     */
    const STATUS_REGISTERED = 'REGISTERED';

    /**
     * Constant representing a managed account.
     *
     * @var string
     */
    const MANAGED = 'MANAGED';

    /**
     * Constant representing an owned account.
     *
     * @var string
     */
    const OWNED = 'OWNED';

    /**
     * Create a new account.
     *
     * @param  string  $email
     * @param  string|null  $businessName
     * @param  string  $type
     * @return \Illuminate\Http\Client\Response
     */
    public function create(string $email, ?string $businessName = null, $type = self::MANAGED)
    {
        $data = compact('email', 'type');

        if ($businessName) {
            data_set($data, 'public_profile.business_name', $businessName);
        }

        return $this->client()->post('/', $data);
    }

    /**
     * Get the account with the given ID.
     *
     * @param  string  $id
     * @return \Illuminate\Http\Client\Response
     */
    public function find(string $id)
    {
        return $this->client()->get("/{$id}");
    }

    /**
     * Update an existing account.
     *
     * @param  string  $id
     * @param  string  $email
     * @param  string|null  $businessName
     * @return \Illuminate\Http\Client\Response
     */
    public function update(string $id, string $email, ?string $businessName = null)
    {
        $data = compact('email');

        if ($businessName) {
            data_set($data, 'public_profile.business_name', $businessName);
        }

        return $this->client()->patch("/{$id}", $data);
    }
}
