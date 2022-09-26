<?php

namespace App\Libraries\Discord;

use Illuminate\Support\Facades\Crypt;
class OAuth extends Api
{
    /**
     * Authorize bot access to user's account.
     *
     * @param  string  $subscriptionId
     * @param  boolean  $override
     * @return \Illuminate\Http\Client\Response
     */
    public function setLink(string $subscriptionId, $override = false) {
        $encrypt = Crypt::encryptString(json_encode([
            'subscription_id' => $subscriptionId,
            'override' => $override
        ]));

        $query = http_build_query([
                "response_type" => "code",
                "client_id" => $this->clientId,
                "scope" => "identify guilds.join",
                "state" => $encrypt,
                "redirect_uri" => $this->redirectUrl,
                "prompt" => 'consent',
            ]);

        return join('/', [$this->apiUrl,'authorize']) .'?'. $query;
    }

    /**
     * Generate auth token.
     *
     * @param  string  $code
     * @return \Illuminate\Http\Client\Response
     */
    public function generateToken(string $code)
    {
         $response = $this->formClient()->post('token', [
            "grant_type" => "authorization_code",
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUrl,
            'code' => $code
        ]);

        return collect(json_decode($response->getBody()->getContents(), true));
    }
}
