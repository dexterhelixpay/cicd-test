<?php

namespace App\Libraries\Xendit;

use App\Libraries\Xendit;
use App\Libraries\Xendit\Requests\EWalletCharge as Request;

class EWalletCharge extends Xendit
{
    /**
     * Constant representing a successful charge.
     *
     * @var string
     */
    const STATUS_SUCCESS = 'SUCCEEDED';

    /**
     * Constant representing a failed charge.
     *
     * @var string
     */
    const STATUS_FAILED = 'FAILED';

    /**
     * Constant representing a pending charge.
     *
     * @var string
     */
    const STATUS_PENDING = 'PENDING';

    /**
     * Constant representing a voided charge.
     *
     * @var string
     */
    const STATUS_VOIDED = 'VOIDED';

    /**
     * Constant representing a refunded charge.
     *
     * @var string
     */
    const STATUS_REFUNDED = 'REFUNDED';
    /**
     * The API version.
     *
     * @var string
     */
    public $version;

    /**
     * Create a new eWallet charge API instance.
     *
     * @param  string  $apiUrl
     * @param  string  $publicKey
     * @param  string  $secretKey
     * @param  string  $version
     * @return void
     */
    public function __construct($apiUrl, $publicKey, $secretKey, $version = '2021-01-25')
    {
        parent::__construct($apiUrl, $publicKey, $secretKey);

        $this->version = $version;
    }

    /**
     * Create an eWallet charge request.
     *
     * @param  \App\Libraries\Xendit\Requests\EWalletCharge  $request
     * @return \Illuminate\Http\Client\Response
     */
    public function create(Request $request)
    {
        $request->validate();

        return $this->client()
            ->withHeaders($request->headers())
            ->post('charges', $request->data());
    }

    /**
     * Get the eWallet charge status.
     *
     * @param  string  $id
     * @param  string|null  $userId
     * @return \Illuminate\Http\Client\Response
     */
    public function find(string $id, ?string $userId = null)
    {
        return $this->client()
            ->when($userId, function ($client, $userId) {
                $client->withHeaders(['for-user-id' => $userId]);
            })
            ->get("charges/{$id}");
    }

    /**
     * Create a new Xendit client.
     *
     * @param  bool  $secret
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function client(bool $secret = true, ?string $userId = null)
    {
        return parent::client($secret, $userId)
            ->withHeaders(['x-api-version' => $this->version]);
    }
}
