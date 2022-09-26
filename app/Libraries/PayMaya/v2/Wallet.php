<?php

namespace App\Libraries\PayMaya\v2;

use App\Libraries\PayMaya\Requests\WalletLink;
use App\Libraries\PayMaya\Requests\WalletTransaction;

class Wallet extends Api
{
    /**
     * Create a wallet link request.
     *
     * @param  \App\Libraries\PayMaya\Requests\WalletLink  $request
     * @param  string  $publicKey
     * @return \Illuminate\Http\Client\Response
     */
    public function create(WalletLink $request, string $publicKey)
    {
        $request->validate();

        return $this->client($publicKey)->post('link', $request->data());
    }

    /**
     * Find the wallet with the given ID.
     *
     * @param  string  $linkId
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function find(string $linkId, string $secretKey)
    {
        return $this->client($secretKey)->get("link/{$linkId}");
    }

    /**
     * Execute a payment transaction using the given wallet.
     *
     * @param  string  $linkId
     * @param  \App\Libraries\PayMaya\Requests\WalletTransaction  $request
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function executePayment(string $linkId, WalletTransaction $request, string $secretKey)
    {
        $request->validate();

        return $this->client($secretKey)
            ->post("link/{$linkId}/execute", $request->data());
    }
}
