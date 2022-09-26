<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Merchant;
use Illuminate\Support\Str;

class ApiKeyService
{
    /**
     * Generate a new API key for the given merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  string|null  $name
     * @return \App\Models\ApiKey
     */
    public function generate(Merchant $merchant, ?string $name = null)
    {
        while (ApiKey::where('prefix', $prefix = Str::random(8))->exists()) {
            //
        }

        $user = request()->userOrClient();
        $secret = Str::random(64);

        ($apiKey = $merchant->apiKeys()->make(['name' => $name ?? 'API Key']))
            ->forceFill([
                'prefix' => $prefix,
                'secret' => $secret,
            ])
            ->createdBy()
            ->associate($user)
            ->save();

        return $apiKey->fresh()->makeVisible('secret');
    }
}
