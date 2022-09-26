<?php

namespace App\Passport;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Passport;
use Laravel\Passport\PersonalAccessTokenFactory as BaseFactory;
use Laravel\Passport\PersonalAccessTokenResult;
use RuntimeException;

class PersonalAccessTokenFactory extends BaseFactory
{
    /**
     * Create a new personal access token.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $user
     * @param  string  $name
     * @param  array  $scopes
     * @return \Laravel\Passport\PersonalAccessTokenResult
     */
    public function make($user, $name, array $scopes = [])
    {
        if (
            method_exists($user, 'guardName')
            && ($provider = config("auth.guards.{$user->guardName()}.provider"))
        ) {
            $name = ucwords(str_replace('_', ' ', $provider)) . ' Personal Access Client';

            $client = Passport::personalAccessClient()
                ->whereHas('client', function ($query) use ($name) {
                    $query->where('name', $name);
                });

            if ($client->doesntExist()) {
                throw new RuntimeException('Personal access client not found. Please create one.');
            }

            $client = $client->first()->client;
        } else {
            $client = $this->clients->personalAccessClient();
        }

        $response = $this->dispatchRequestToAuthorizationServer(
            $this->createRequest($client, $user->getKey(), $scopes)
        );

        $token = tap($this->findAccessToken($response), function ($token) use ($user, $name) {
            $this->tokens->save($token->forceFill([
                'user_id' => $user->getKey(),
                'name' => $name,
            ]));
        });

        return new PersonalAccessTokenResult(
            $response['access_token'], $token
        );
    }
}
