<?php

namespace App\Libraries\Discord;

use App\Facades\Discord;

class Guild extends Api
{
    /**
     * Get all the channels on the server.
     *
     * @param  string  $guildId
     * @return \Illuminate\Http\Client\Response
     */
    public function channels(string $guildId)
    {
        return $this->client($this->botToken)->get("{$guildId}/channels");
    }

    /**
     * Get all the roles on the server.
     *
     * @param  string  $guildId
     * @return \Illuminate\Http\Client\Response
     */
    public function roles(string $guildId)
    {
        return $this->client($this->botToken)->get("{$guildId}/roles");
    }

    /**
     * Add channel to the guild.
     *
     * @param  string  $guildId
     * @param  string  $type
     * @param  string  $name
     * @param  string|null  $roleId
     * @return \Illuminate\Http\Client\Response
     */
    public function addChannel(
        string $guildId,
        string $type,
        string $name,
        string $parentId = null,
        string $roleId = null
    ) {
        $permission =  $type == Discord::GUILD_CATEGORY
            ? []
            : [
                [
                    'id' => $roleId,
                    'type' => '0',
                    'allow' => '1024',
                    'deny' => '0'
                ],
                [
                    'id' => $guildId,
                    'type' => '0',
                    'allow' => '0',
                    'deny' => '1024'
                ]
            ];

        return $this->client($this->botToken)
            ->post("{$guildId}/channels", [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
                'permission_overwrites' => $permission
            ]);
    }

    /**
     * Add guild role.
     *
     * @param  string  $guildId
     * @param  string  $name
     * @return \Illuminate\Http\Client\Response
     */
    public function addRole(
        string $guildId,
        string $name
    ) {
        return $this->client($this->botToken)
            ->post("{$guildId}/roles", [
                'name' => $name,
                'permissions' => Discord::DEFAULT_PERMISSION
            ]);
    }

    /**
     * Modify guild role.
     *
     * @param  string  $guildId
     * @param  string  $name
     * @return \Illuminate\Http\Client\Response
     */
    public function modifyRole(
        string $guildId,
        string $roleId
    ) {
        return $this->client($this->botToken)
            ->patch("{$guildId}/roles/{$roleId}", [
                'permissions' => Discord::DEFAULT_PERMISSION
            ]);
    }

    /**
     * Add user to the guild.
     *
     * @param  string  $guildId
     * @param  string  $code
     * @return \Illuminate\Http\Client\Response
     */
    public function addUser(string $guildId, string $code)
    {
        $token = Discord::oAuth()->generateToken($code);
        $user = Discord::users()->getAuthUser($token['access_token']);

        $response = $this->client($this->botToken)
            ->put("{$guildId}/members/{$user['id']}", [
                'access_token' => $token['access_token']
            ]);

        if ($response->successful()) {
            return $response->json()
                ? data_get($response->json(), 'user')
                : $user;
        }

        $response->throw();
    }

    /**
     * Remove user from the guild.
     *
     * @param  string  $guildId
     * @param  string  $userId
     * @return \Illuminate\Http\Client\Response
     */
    public function removeUser(string $guildId, string $userId)
    {
        $response = $this->client($this->botToken)
            ->delete("{$guildId}/members/{$userId}");
    }

    /**
     * Add user role to the guild.
     *
     * @param  string  $guildId
     * @param  string  $userId
     * @param  string  $role
     * @return \Illuminate\Http\Client\Response
     */
    public function addUserRole(string $guildId, string $userId, string $role)
    {
        return $this->client($this->botToken)
            ->put("{$guildId}/members/{$userId}/roles/{$role}");
    }

    /**
     * Remove user role to the guild.
     *
     * @param  string  $guildId
     * @param  string  $userId
     * @param  string  $role
     * @return \Illuminate\Http\Client\Response
     */
    public function removeUserRole(string $guildId, string $userId, string $role)
    {
        return $this->client($this->botToken)
            ->delete("{$guildId}/members/{$userId}/roles/{$role}");
    }
}
