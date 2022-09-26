<?php

namespace App\Libraries\Xendit;

use App\Libraries\Xendit;

class CallbackUrl extends Xendit
{
    /**
     * Constant representing an eWallet callback.
     *
     * @var string
     */
    const TYPE_EWALLET = 'ewallet';

    /**
     * Set the callback URL for the given type.
     *
     * @param  string  $type
     * @param  string  $url
     * @param  string  $userId
     * @return \Illuminate\Http\Client\Response
     */
    public function set(string $type, string $url, ?string $userId = null)
    {
        return $this->client()
            ->when($userId, function ($client, $userId) {
                $client->withHeaders(['for-user-id' => $userId]);
            })
            ->post($type, compact('url'));
    }
}
