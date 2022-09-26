<?php

namespace App\Providers;

use App\Channels\CacheChannel;
use App\Channels\GlobeLabsSmsChannel;
use App\Channels\M360Channel;
use App\Channels\MerchantViberChannel;
use App\Channels\SendGridChannel;
use App\Channels\SlackWebhookChannel;
use App\Channels\ViberChannel;
use App\Exports\CustomersTemplate;
use App\Libraries\Brankas\Brankas;
use App\Libraries\Cloudflare\Zone;
use App\Libraries\Discord;
use App\Libraries\PayMaya;
use App\Libraries\PayMaya\PayMaya as OldPayMaya;
use App\Libraries\PayMongo\PayMongo;
use App\Libraries\PesoPay;
use App\Libraries\Shopify;
use App\Libraries\Viber\Viber;
use App\Libraries\Xendit;
use App\Models\Customer;
use App\Models\MerchantUser;
use App\Models\User;
use App\Rules\CheckoutHash;
use App\Rules\EmailAttachment;
use App\Rules\MobileNumber;
use App\Rules\ObjectArray;
use App\Rules\Slug;
use App\Rules\Subdomain;
use App\Rules\VideoUrl;
use BenMorel\GsmCharsetConverter\Converter;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use Lcobucci\JWT\Parser;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use SendGrid;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cloudflare.zone', function () {
            return new Zone(
                $this->app['config']['services.cloudflare.zone_id']
            );
        });

        $this->app->singleton('gsm.converter', function () {
            return new Converter;
        });

        $this->app->singleton('paymaya', function () {
            return new PayMaya($this->app['config']['services.paymaya.api_url']);
        });

        $this->app->singleton('paymaya.old', function () {
            return new OldPayMaya(
                $this->app['config']['services.paymaya.api_url'],
                $this->app['config']['services.paymaya.pwp'],
                $this->app['config']['services.paymaya.vault']
            );
        });

        $this->app->singleton('pesopay', function () {
            return new PesoPay(
                $this->app['config']['services.pesopay.api_url'],
                $this->app['config']['services.pesopay.merchant_id'],
                $this->app['config']['services.pesopay.secure_hash_secret']
            );
        });

        $this->app->singleton('shopify', function () {
            return new Shopify('', '');
        });

        $this->app->singleton('vimeo', function () {
            return new \App\Libraries\Vimeo(
                $this->app['config']['services.vimeo.api_url'],
                $this->app['config']['services.vimeo.client_id'],
                $this->app['config']['services.vimeo.client_secret'],
                $this->app['config']['services.vimeo.access_token']
            );
        });

        $this->app->singleton('brankas', function () {
            return new Brankas(
                $this->app['config']['services.brankas.api_url'],
                $this->app['config']['services.brankas.api_key'],
            );
        });

        $this->app->singleton('viber', function () {
            return new Viber(
                $this->app['config']['services.viber.api_url'],
                $this->app['config']['services.viber.auth_token'],
                $this->app['config']['services.viber.sender_name'],
                $this->app['config']['services.viber.sender_avatar']
            );
        });

        $this->app->singleton('xendit', function () {
            return new Xendit(
                $this->app['config']['services.xendit.api_url'],
                $this->app['config']['services.xendit.public_key'],
                $this->app['config']['services.xendit.secret_key']
            );
        });

        $this->app->singleton('paymongo', function () {
            return new PayMongo(
                join('/', [
                    $this->app['config']['services.paymongo.api_url'],
                    $this->app['config']['services.paymongo.api_version']
                ]),
                $this->app['config']['services.paymongo.secret_key']
            );
        });

        $this->app->singleton('discord', function () {
            return new Discord(
                $this->app['config']['services.discord.api_url'],
                $this->app['config']['services.discord.redirect_url'],
                $this->app['config']['services.discord.client_id'],
                $this->app['config']['services.discord.client_secret'],
                $this->app['config']['services.discord.bot_token']
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Collection::macro('isAssoc', function () {
            /** @var Collection $this */
            $keys = $this->keys()->toArray();

            return array_keys($keys) !== $keys;
        });

        Collection::macro('isList', function () {
            /** @var Collection $this */
            return !$this->isAssoc();
        });

        Collection::macro('getAsset', function ($key, $default = null) {
            /** @var Collection $this */
            $value = $this->get($key, $default);

            return $value ? Storage::url($value) : null;
        });

        HeadingRowFormatter::extend('customers', function ($value, $key) {
            return in_array($value, (new CustomersTemplate)->headings())
                ? Str::slug($value, '_')
                : Str::camel($value);
        });

        Notification::resolved(function (ChannelManager $service) {
            $service->extend('globelabs', function () {
                return new GlobeLabsSmsChannel;
            });

            $service->extend('m360', function () {
                return new M360Channel(
                    $this->app['config']['services.m360.api_url'],
                    $this->app['config']['services.m360.username'],
                    $this->app['config']['services.m360.password'],
                    $this->app['config']['services.m360.shortcode_mask']
                );
            });

            $service->extend('sendgrid', function () {
                return new SendGridChannel(
                    new SendGrid($this->app['config']['services.sendgrid.key'])
                );
            });

            $service->extend('viber', function () {
                return new ViberChannel;
            });

            $service->extend('merchantviber', function () {
                return new MerchantViberChannel;
            });

            $service->extend('slack', function ($app) {
                return new SlackWebhookChannel($app->make(HttpClient::class));
            });

            $service->extend('cache', function () {
                return new CacheChannel;
            });
        });

        Request::macro('client', function () {
            /** @var Request $this */
            if (!$accessToken = $this->bearerToken()) {
                return null;
            }

            try {
                $token = app(Parser::class)->parse($accessToken);
                $accessTokenId = $token->claims()->get('jti');
                return Token::findOrFail($accessTokenId)->client()->first();
            } catch (Throwable $e) {
                return null;
            }
        });

        Request::macro('hasOnly', function ($keys, string $context = null) {
            $keys = Arr::wrap($keys);
            $input = $context
                ? Arr::get(request()->all(), $context)
                : request()->all();

            return Arr::has($input, $keys) && count(Arr::except($input, $keys)) === 0;
        });

        Request::macro('from', function ($asString = true) {
            /** @var Request $this */
            $user = $this->user();
            $client = $this->client();

            try {
                $isClient = $client && $client->user()->exists();
            } catch (Throwable $e) {
                $isClient = false;
            }
            $userOrClient = $user ?: optional($client)->user;

            $arrayResult = [
                'is_client' => $isClient,
                'is_guest' => !$userOrClient,
                'user' => $userOrClient,
                'user_class' => $userOrClient ? get_class($userOrClient) : null,
            ];

            if (!$asString) {
                return $arrayResult;
            }

            if ($isClient) {
                return 'client';
            }

            if ($userOrClient instanceof Customer) {
                return 'customer';
            }

            if ($userOrClient instanceof MerchantUser) {
                return 'merchant';
            }

            if ($userOrClient instanceof User) {
                return 'user';
            }

            return 'guest';
        });

        Request::macro('isFromClient', function () {
            /** @var Request $this */
            if ($client = $this->client()) {
                return $client->user()->exists();
            }

            return false;
        });

        Request::macro('isFromCustomer', function () {
            /** @var Request $this */
            $user = $this->userOrClient();

            return $user instanceof Customer ? $user : false;
        });

        Request::macro('isFromGuest', function () {
            /** @var Request $this */
            return is_null($this->userOrClient());
        });

        Request::macro('isFromMerchant', function () {
            /** @var Request $this */
            $user = $this->userOrClient();

            return $user instanceof MerchantUser ? $user : false;
        });

        Request::macro('isFromUser', function () {
            /** @var Request $this */
            $user = $this->userOrClient();

            return $user instanceof User ? $user : false;
        });

        Request::macro('userOrClient', function () {
            /** @var Request $this */
            if ($user = $this->user()) {
                return $user;
            }

            return optional($this->client())->user;
        });

        Request::macro('inputAttribute', function ($attribute, $default = null) {
            /** @var Request $this */
            return $this->input('data.attributes.' . trim($attribute, " \t\n\r\0\x0B."), $default);
        });

        HasMany::macro('sync', function (array $data, $deleting = true) {
            /** @var HasMany $this */

            $changes = [
                'created' => [], 'deleted' => [], 'updated' => [],
            ];

            $relatedKeyName = $this->getRelated()->getKeyName();

            $current = $this->newQuery()->pluck($relatedKeyName)->all();

            $castKey = function ($value) {
                if (is_null($value)) {
                    return $value;
                }

                return is_numeric($value) ? (int) $value : (string) $value;
            };

            $castKeys = function ($keys) use ($castKey) {
                return (array) array_map(function ($key) use ($castKey) {
                    return $castKey($key);
                }, $keys);
            };

            $deletedKeys = array_diff($current, $castKeys(Arr::pluck($data, $relatedKeyName)));

            if ($deleting && count($deletedKeys) > 0) {
                $this->getRelated()->destroy($deletedKeys);
                $changes['deleted'] = $deletedKeys;
            }

            $newRows = Arr::where($data, function ($row) use ($relatedKeyName) {
                return null === Arr::get($row, $relatedKeyName);
            });

            $updatedRows = Arr::where($data, function ($row) use ($relatedKeyName) {
                return null !== Arr::get($row, $relatedKeyName);
            });

            if (count($newRows) > 0) {
                $newRecords = $this->createMany($newRows);
                $changes['created'] = $castKeys(
                    $newRecords->pluck($relatedKeyName)->toArray()
                );
            }

            foreach ($updatedRows as $row) {
                $this->getRelated()->where($relatedKeyName, $castKey(Arr::get($row, $relatedKeyName)))
                    ->first()
                    ->update($row);
            }

            $changes['updated'] = $castKeys(Arr::pluck($updatedRows, $relatedKeyName));

            return $changes;
        });

        UrlGenerator::macro('signedUrl', function ($url, $parameters = [], $expiration = null) {
            /** @var UrlGenerator $this */
            $this->ensureSignedRouteParametersAreNotReserved(
                $parameters = Arr::wrap($parameters)
            );

            if ($expiration) {
                $parameters = $parameters + ['expires' => $this->availableAt($expiration)];
            }

            ksort($parameters);

            $key = call_user_func($this->keyResolver);

            $url = rtrim(trim($url), '/');
            $query = count($parameters) ? "/?" . Arr::query($parameters) : '';

            return "{$url}/?" . Arr::query($parameters + [
                'signature' => hash_hmac('sha256', $url . $query, $key),
            ]);
        });

        Validator::extend('checkout_hash', CheckoutHash::class . '@passes');
        Validator::extend('mobile_number', MobileNumber::class . '@passes');
        Validator::extend('object_array', ObjectArray::class . '@passes');
        Validator::extend('slug', Slug::class . '@passes');
        Validator::extend('subdomain', Subdomain::class . '@passes');
        Validator::extend('video_url', VideoUrl::class . '@passes');
        Validator::extend('email_attachment', EmailAttachment::class . '@passes');

        if (!app()->isLocal()) {
            URL::forceScheme('https');
        }
    }
}
