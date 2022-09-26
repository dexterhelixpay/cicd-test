<?php

namespace App\Http\Controllers\Api\v1\Discord;

use Exception;
use Throwable;
use App\Models\Product;
use App\Facades\Discord;
use App\Models\Merchant;
use Illuminate\Support\Str;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\SubscribedProduct;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiscordRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use App\Exceptions\DiscordUsedLinkException;
use App\Exceptions\DiscordUserOverrideException;
use Illuminate\Support\Facades\Storage;

class DiscordController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('configureDiscordServer');
        $this->middleware('auth:user,customer')->only('overrideUser');
        $this->middleware('auth:user')->only('modifyGuildPermissions');
    }

    /**
     * Configure discord server product's channel
     *
     * @param  \Illuminate\Http\DiscordRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function configureDiscordServer(DiscordRequest $request, Merchant $merchant)
    {
        try {
            $guildId = data_get($request,'data.attributes.guild_id');
            $productIds = data_get($request, 'data.attributes.product_ids');

            $channels = Discord::guilds()
                ->channels($guildId)
                ->json();

            $this->setDefaultPermissions($guildId);

            $category = collect($channels)->contains('name', 'Products')
                ? collect($channels)->where('name', 'Products')->first()
                : Discord::guilds()
                    ->addChannel(
                        $guildId,
                        Discord::GUILD_CATEGORY,
                        'Products'
                    )
                    ->json();

            $merchant->products()
                ->when($productIds, function ($query) use($productIds) {
                    $query->whereIn('id', $productIds);
                })
                ->where('is_visible', true)
                ->get()
                ->each(function(Product $product) use ($guildId, $category) {
                    if ($product->discord_role_id) return;

                    if (!$product->slug) {
                        $product->slug = $this->setSlug($product->title);
                        $product->saveQuietly();
                    }

                    $this->createRole($product, $guildId);
                    $productRole = $this->getProductRole($product->slug, $guildId);

                    $channel = Discord::guilds()
                        ->addChannel(
                            $guildId,
                            Discord::GUILD_TEXT,
                            $product->slug,
                            data_get($category, 'id'),
                            data_get($productRole, 'id'),
                        );

                    $product->fill([
                            'is_discord_invite_enabled' => true,
                            'discord_channel' => data_get($channel, 'name'),
                            'discord_role_id' => data_get($productRole, 'id')
                        ])
                        ->saveQuietly();
                });

            return response()->json([
                'message' => 'Discord server setup completed!',
                'status' => 200
            ]);

        } catch (Throwable $e) {
            Log::info($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Redirect the request to discord bot authorization page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request)
    {
        try {
            if ($request->isNotFilled('subscription_id')) {
                throw new Exception;
            }

            $subscription = Subscription::whereKey($request->input('subscription_id'))
                ->firstOrFail();

            if (!$subscription?->merchant->is_discord_email_invite_enabled) {
                return view('discord', [
                    'imagePath' => Storage::url('images/assets/discord_icon.png')
                ]);
            }

            if ($subscription->discord_user_id) {
                throw new DiscordUsedLinkException('error');
            }

            return redirect(
                    Discord::oAuth()->setLink($subscription->id)
                );

        } catch (Throwable $e) {
            Log::info($e->getMessage());
            if ($e instanceof DiscordUsedLinkException) {
                abort(
                    403,
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Get the access token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function accessTokenExchange(Request $request)
    {
        try {

            if ($request->isNotFilled('code')) {
                throw new Exception;
            }

            $data = json_decode(
                Crypt::decryptString($request->input('state'))
            );

            $subscription = Subscription::whereKey($data->subscription_id)
                ->firstOrFail();

            $user = Discord::guilds()
                ->addUser(
                    $subscription->merchant->discord_guild_id,
                    $request->input('code')
                );

            if ($user) {
                if (data_get($data, 'override')) {
                    if ($user['id'] == $subscription->customer->discord_user_id ) {
                        throw new DiscordUserOverrideException;
                    }

                    $this->kickUser($subscription);
                }

                if (
                    !$subscription->customer->discord_user_id
                    || $subscription->customer->discord_user_id != $user['id']
                ) {
                    $subscription->customer->fill([
                            'discord_user_id' => $user['id'],
                            'discord_user_username' => $user['username']
                        ])
                        ->saveQuietly();
                }

                $subscription->fill([
                        'discord_user_id' => $user['id'],
                    ])
                    ->saveQuietly();

                $subscription->products
                    ->each(function(SubscribedProduct $subscribedProduct) {
                        return $subscribedProduct->update([
                            'is_active_discord_member' => true
                        ]);
                    })
                    ->pluck('product')
                    ->each(function(Product $product) use ($subscription, $data) {
                        if (!$product->discord_channel) return;

                        Discord::guilds()
                            ->addUserRole(
                                $subscription->merchant->discord_guild_id,
                                $subscription->discord_user_id,
                                $product->discord_role_id
                            );
                    });

                return Redirect::away("https://discord.com/channels/{$subscription->merchant->discord_guild_id}");
            }
        } catch (Throwable $e) {
            Log::info($e->getMessage());
            if ($e instanceof DiscordUserOverrideException) {
                return $this->redirectToCustomerProfile($subscription->merchant, $e->getMessage());
            }
        }
    }

    /**
     * Request authorization from user to override
     *
     * @param  \Illuminate\Http\DiscordRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function overrideUser(DiscordRequest $request)
    {
        try {
            if ($request->isNotFilled('data.attributes.subscription_id')) {
                throw new Exception;
            }

            return response()->json([
                    'redirect_url' => Discord::oAuth()->setLink(
                        $request->input('data.attributes.subscription_id'),
                        true
                    )
                ]);

        } catch (Throwable $e) {
            Log::info($e->getMessage());
            throw new Exception;
        }
    }

    /**
     * Kick discord user
     *
     * @param  \App\Models\Subscription $subscription
     * @return \Illuminate\Http\RedirectResponse
     */
    public function kickUser(Subscription $subscription)
    {
        return Discord::guilds()->removeUser(
            $subscription->merchant->discord_guild_id,
            $subscription->discord_user_id
        );
    }

    /**
     * Redirect to customer profile
     *
     * @param  object|array  $data
     * @param  string  $accessToken
     */
    public function redirectToCustomerProfile($merchant, $message = null)
    {
        $scheme = app()->isLocal() ? 'http' : 'https';
        $stateUrl = config('bukopay.url.profile');

        $url = "{$scheme}://{$merchant->subdomain}.{$stateUrl}";
        if ($message) {
            $url = $url .'?'. http_build_query([
                'error' => $message,
                'isFromDiscord' => true
            ]);
        }

        return Redirect::away($url);
    }

    /**
     * Create discord guild role
     *
     * @param  \App\Models\Product  $product
     * @param  string  $guildId
     */
    public function createRole(Product $product, $guildId)
    {
        $role = Discord::guilds()
            ->addRole(
                $guildId,
                $product->slug
            );

        return $role->json();
    }

    /**
     * Get the product role
     *
     * @param  string  $slug
     * @param  string  $guildId
     */
    public function getProductRole($slug, $guildId)
    {
        $roles = Discord::guilds()
            ->roles($guildId);

        return collect($roles->json())
                ->where('name', $slug)
                ->first();
    }

    /**
     * Set default @everyone role permissions
     *
     * @param  string  $guildId
     */
    public function setDefaultPermissions($guildId)
    {
        Discord::guilds()
            ->modifyRole($guildId, $guildId);
    }

    /**
     * Update guild permissions
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function modifyGuildPermissions(Request $request)
    {
        try {
            $merchant = Merchant::findOrFail($request->input('data.attributes.merchant_id'));

            $this->setDefaultPermissions($merchant->discord_guild_id);

            $merchant->products()
                ->whereNotNull('discord_role_id')
                ->get()
                ->each(function(Product $product) use ($merchant) {
                    Discord::guilds()
                        ->modifyRole(
                            $merchant->discord_guild_id,
                            $product->discord_role_id
                        );
                });

            return response()->json([
                'message' => 'Discord channel permissions updated!',
                'status' => 200
            ]);
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Set the slug
     *
     * @param  $title
     *
     * @return string
     */
    protected function setSlug($title)
    {
        $slug = Str::slug($title, '-');

        $checkDuplicate = Product::where('slug', $slug)->first();

        if ($checkDuplicate) {
            $slug = $this->reNameSlug($slug);
        }

        return $slug;
    }

    /**
     * Rename the duplicated slug.
     *
     * @param  string  $slug
     * @param  int  $count
     *
     * @return string
     */
    protected function reNameSlug($slug, $count = 0)
    {
        $mainSlug = $slug;

        if ($count === 0 ) {
            $count += 1;
        }

        $checkDuplicate = Product::firstWhere('slug', "{$mainSlug}-{$count}");

        if ($checkDuplicate) {
            $count++;
            return $this->reNameSlug($mainSlug, $count);
        }

        return $mainSlug."-{$count}";
    }

}
