<?php

namespace App\Http\Controllers\Api\v1;

use Exception;
use Throwable;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Country;
use App\Models\Voucher;
use App\Support\Prices;
use App\Libraries\Image;
use App\Mail\EmailBlast;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Province;
use App\Imports\Finances;
use App\Models\PaymentType;
use App\Models\PricingType;
use App\Rules\MobileNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\MerchantUser;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Mail\PaymentReminder;
use App\Models\DraftMerchant;
use App\Models\PaymentStatus;
use App\Models\ShippingMethod;
use App\Models\ConvenienceType;
use App\Support\ConvenienceFee;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Http\Resources\PriceResource;
use App\Models\MerchantFollowUpEmail;
use Laravel\Passport\ClientRepository;
use App\Exceptions\BadRequestException;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Order\OrderImport;
use App\Http\Resources\ResourceCollection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\UnauthorizedException;
use App\Notifications\TestEmailBlastNotification;
use App\Notifications\MerchantVerifiedNotification;
use App\Notifications\TestReminderEmailNotification;
use App\Notifications\TestSubscriptionEmailNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user')->only('destroy');
        $this->middleware('auth:user,merchant')->only('update');
        $this->middleware('auth:user,merchant,customer,null')->only('show', 'store', 'getComputedOrderPrices', '');
        $this->middleware('permission:CP: Merchants - Edit')->only('destroy');
        $this->middleware(
                'permission:CP: Merchants - Edit|MC: Settings|CP: Merchants - Log in to Store'
            )
            ->only('update');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $merchants = QueryBuilder::for(Merchant::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($merchants);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validateRequest($request);

        return DB::transaction(function () use ($request) {
            $password = bcrypt($request->input('data.attributes.password'));

            $merchant = Merchant::make()
                ->fill(Arr::except($request->input('data.attributes'), 'password'))
                ->setAttribute('password', $password);

            $merchant->save();

            $merchant->users()
                ->make(Arr::except($request->input('data.attributes'), 'password'))
                ->setAttribute('password', $password)
                ->assignRole('Owner')
                ->markEmailAsVerified();

            $merchant->users()
                ->first()
                ->syncPermissions(
                    Role::where('name','Owner')
                        ->first()
                        ->getAllPermissions()
                );

            if (
                $request->filled('data.relationships.description_items.data')
                || $request->hasFile('data.relationships.description_items.data.*.attributes.icon')
            ) {
                $items = $request->input('data.relationships.description_items.data', [])
                    + $request->file('data.relationships.description_items.data', []);

                $merchant->syncDescriptionItems($items);
            }

            if (!app()->environment('production')) {
                $merchant->setAttribute('is_credentials_visible', true)
                    ->save();

                $client = (new ClientRepository)->create(
                    $merchant->owner()->first()->getKey(),
                    "{$merchant->name} Client",
                    'http://localhost',
                    'merchant_users'
                );
                $client->makeVisible('secret');
            }

            $this->createShippingMethods($merchant);

            DraftMerchant::whereId($request->input('data.attributes.draft_merchant_id'))
                ->delete();

            return new CreatedResource($merchant->fresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant)
    {
        $merchant = QueryBuilder::for(Merchant::class)
            ->whereKey($merchant->getKey())
            ->apply()
            ->first();

        if (!$merchant) {
            throw (new ModelNotFoundException)->setModel(Merchant::class);
        }

        return new Resource($merchant);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $merchant)
    {
        $merchant = Merchant::findOrFail($merchant);

        if ($request->filled('data.attributes.is_verified')) {
            return $this->verify($request, $merchant);
        }

        if ($request->filled('data.attributes.is_logged_in_as_user')) {
            return $this->loginAsMerchant($request, $merchant);
        }

        if ($request->filled('data.attributes.copies')) {
            return $this->updateCopies($request, $merchant);
        }

        if ($request->has('data.relationships.payment_types')) {
            $merchant->update($request->input('data.attributes', []));
            return $this->syncPaymentTypes($request, $merchant);
        }

        if ($request->has('data.relationships.table_columns')) {

            if (!$request->filled('data.relationships.table_columns.data')) {
                $merchant->tableColumns()->sync([]);
            }

            $merchant->tableColumns()->sync(
                collect($request->input('data.relationships.table_columns.data'))
                    ->mapWithKeys(function ($column, $index) {
                        return [
                            $column['id'] => [
                                'table' => data_get($column, 'table'),
                                'sort_number' =>  $index + 1,
                            ]
                        ];
                })
            );
            return $merchant->load('tableColumns');
        }

        if ($request->has('data.attributes.custom_domain')) {
            return $this->setupCustomDomain($request, $merchant);
        }

        if ($request->input('data.attributes.is_verifying_custom_domain')) {
            return $this->verifyCustomDomain($request, $merchant);
        }

        $this->authorizeRequest($request, $merchant);
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $data = Arr::except($request->input('data.attributes', []), 'password');
            if ($request->filled('data.attributes.password')) {
                $data['password'] = bcrypt($request->input('data.attributes.password'));
            }

            if (
                $request->isFromUser()
                && $request->hasAny(
                    'data.attributes.paymaya_vault_mid_id',
                    'data.attributes.paymaya_pwp_mid_id',
                    'data.attributes.paymaya_pwp_mid_console_id',
                    'data.attributes.paymaya_vault_mid_console_id'
                )
            ) {
                $merchant->forceFill(Arr::only($request->input('data.attributes', []), [
                    'paymaya_vault_mid_id',
                    'paymaya_pwp_mid_id',
                    'paymaya_pwp_mid_console_id',
                    'paymaya_vault_mid_console_id'
                ]));
            }

            $merchant->fill($data);

            if ($request->hasFile('data.attributes.logo')) {
                $merchant->uploadLogo(
                    $request->file('data.attributes.logo'),
                    $request->file('data.attributes.logo')->getClientOriginalExtension()
                );
            } elseif (
                $request->has('data.attributes.logo')
                && is_null($request->input('data.attributes.logo'))
            ) {
                $merchant->forceFill(['logo_image_path' => null]);
            }

            if ($request->hasFile('data.attributes.members_login_image')) {
                $merchant->uploadMembersLoginImage(
                    $request->file('data.attributes.members_login_image'),
                    $request->file('data.attributes.members_login_image')->getClientOriginalExtension()
                );
            } elseif (
                $request->has('data.attributes.members_login_image')
                && is_null($request->input('data.attributes.members_login_image'))
            ) {
                $merchant->forceFill(['members_login_banner_path' => null]);
            }

            if ($request->hasFile('data.attributes.soldout_banner')) {
                $merchant->uploadImage(
                    $request->file('data.attributes.soldout_banner'), 'soldout_banner'
                );
            } elseif (
                $request->has('data.attributes.soldout_banner')
                && is_null($request->input('data.attributes.soldout_banner'))
            ) {
                $merchant->images?->forget('soldout_banner');
            }

            if ($request->hasFile('data.attributes.svg_logo')) {
                $merchant->uploadLogo($request->file('data.attributes.svg_logo'), 'svg');
            } elseif ($request->filled('data.attributes.svg_logo')) {
                $svgLogo = base64_decode($request->input('data.attributes.svg_logo'));

                $merchant->uploadLogo($svgLogo, 'svg');

            } elseif (
                $request->has('data.attributes.svg_logo')
                && is_null($request->input('data.attributes.svg_logo'))
            ) {
                $merchant->forceFill(['logo_svg_path' => null]);
            }

            if ($request->hasFile('data.attributes.favicon')) {
                $merchant->uploadFavicon($request->file('data.attributes.favicon'));
            } elseif (
                $request->has('data.attributes.favicon')
                && is_null($request->input('data.attributes.favicon'))
            ) {
                $merchant->forceFill(['favicon_path' => null]);
            }

            if ($request->hasFile('data.attributes.home_banner')) {
                $merchant->uploadHomeBanner($request->file('data.attributes.home_banner'));
            } else if ($request->has('data.attributes.home_banner_path')) {
                $merchant->forceFill([
                    'home_banner_path' => $request->input('data.attributes.home_banner_path')
                ]);
            } elseif (
                $request->has('data.attributes.home_banner')
                && is_null($request->input('data.attributes.home_banner'))
            ) {
                $merchant->forceFill(['home_banner_path' => null]);
            }

            if ($request->hasFile('data.attributes.membership_banner')) {
                $merchant->uploadMembershipBanner($request->file('data.attributes.membership_banner'));
            } elseif (
                $request->has('data.attributes.membership_banner')
                && is_null($request->input('data.attributes.membership_banner'))
            ) {
                $merchant->forceFill(['membership_banner_path' => null]);
            }

            if ($request->hasFile('data.attributes.marketing_card_image_path')) {
                $merchant->uploadMarketingCardImage(
                    $request->file('data.attributes.marketing_card_image_path')
                );
            } elseif (
                $request->has('data.attributes.marketing_card_image_path')
                && is_null($request->input('data.attributes.marketing_card_image_path'))
            ) {
                $merchant->forceFill([
                    'marketing_card_image_path' => null,
                    'marketing_card_expires_at' => null,
                    'marketing_card_image_url' => null,
                ]);
            }

            if ($request->hasFile('data.attributes.customer_promo_image_path')) {
                $merchant->uploadCustomerPromoImage($request->file('data.attributes.customer_promo_image_path'));
            } elseif (
                $request->has('data.attributes.customer_promo_image_path')
                && is_null($request->input('data.attributes.customer_promo_image_path'))
            ) {
                $merchant->forceFill(
                    [
                        'customer_promo_image_path' => null,
                        'customer_promo_image_url' => null,
                    ]
                );
            }

            if ($request->hasFile('data.attributes.storefront_background_image_path')) {
                $merchant->uploadBackgroundImage($request->file('data.attributes.storefront_background_image_path'));
            } elseif (
                $request->has('data.attributes.storefront_background_image_path')
                && is_null($request->input('data.attributes.storefront_background_image_path'))
            ) {
                $merchant->forceFill(['storefront_background_image_path' => null]);
            }

            $merchant->update();

            if (
                $request->filled('data.relationships.description_items.data')
                || $request->hasFile('data.relationships.description_items.data.*.attributes.icon')
            ) {
                $merchant->syncDescriptionItems(data_get($request, 'data.relationships.description_items.data'));
            } elseif (
                $request->has('data.relationships.description_items.data')
                && is_null($request->input('data.relationships.description_items.data'))
            ) {
                $merchant->descriptionItems()->get()->each->delete();
            }

            if (
                $request->filled('data.relationships.social_links.data')
            ) {
                $merchant->syncSocialLinks(data_get($request, 'data.relationships.social_links.data'));
            } elseif (
                $request->has('data.relationships.social_links.data')
                && is_null($request->input('data.relationships.social_links.data'))
            ) {
                $merchant->socialLinks()->get()->each->delete();
            }

            if (
                $request->isFromUser()
                && $request->filled('data.relationships.owner.data.attributes')
            ) {
                $data = Arr::except(
                    $request->input('data.relationships.owner.data.attributes'),
                    'password'
                );

                if ($request->filled('data.relationships.owner.data.attributes.password')) {
                    $data['password'] = bcrypt(
                        $request->input('data.relationships.owner.data.attributes.password')
                    );
                }

                $merchant->owner()->first()->update($data);
            }

            return new Resource($merchant->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $merchant)
    {
        $merchant = Merchant::find($merchant);

        if (!optional($merchant)->delete()) {
            throw (new ModelNotFoundException)->setModel(Merchant::class);
        }

        return response()->json([], 204);
    }

    /**
     * Compute the fees and pricess of orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array||object  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public static function getComputedOrderPrices(Request $request,Merchant $merchant)
    {
        $request->validate([
            'data.attributes' => 'required',
            'data.attributes.products' => [
                Rule::requiredIf($request->input('data.type') == 'before-subscription')
            ],
            'data.attributes.order_id' => [
                Rule::requiredIf($request->input('data.type') == 'subscribed'),
                'nullable',
                Rule::exists('orders', 'id')->withoutTrashed(),
            ],
            'data.attributes.customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->withoutTrashed(),
            ],
            'data.attributes.shipping_method_id' => [
                'nullable',
                'sometimes',
                Rule::exists('shipping_methods', 'id')
                    ->where('merchant_id', $merchant->id),
            ],
        ]);

        $data = Prices::compute(
            type: data_get($request, 'data.type'),
            order: Order::find(data_get($request, 'data.attributes.order_id')),
            customer: Customer::find($request->userOrClient()?->id ?? data_get($request, 'data.attributes.customer_id')),
            merchant: $merchant,
            paymentTypeId: data_get($request, 'data.attributes.payment_type_id'),
            isAutoCharge: data_get($request, 'data.attributes.is_auto_charge', false),
            bankCode: data_get($request, 'data.attributes.bank_code', null),
            products: collect(data_get($request,'data.attributes.products')),
            isFromApiCheckout: data_get($request,'data.attributes.is_from_api_checkout'),
            isFromConsole: data_get($request,'data.attributes.is_from_console'),
            shippingMethodId: data_get($request, 'data.attributes.shipping_method_id'),
            voucherCode: data_get($request, 'data.attributes.voucher_code'),
            isShopifyBooking: data_get($request,'data.attributes.is_shopify_booking'),
            bankFee: data_get($request, 'data.attributes.bank_fee')
        );

        return new PriceResource($data);
    }


    /**
     * Import finances from the specified file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importFinances(Request $request)
    {
        $request->validate([
           'data.finances'  => 'required|mimes:csv,xlsx'
        ]);

        (new Finances)->import($request->file('data.finances'));

        return $this->okResponse();
    }

    /**
     * Generate Slug
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateSlug(Request $request)
    {
        $storeCount = MerchantUser::query()
            ->where('username', $request->input('data.store_name'))
            ->count();

        $slug = Str::slug($request->input('data.store_name'), '-');

        $slug .= $storeCount > 0
            ? '-'.$storeCount
            : '';

        return response()->json(compact('slug'));
    }

    /**
     * Sync Payment Types
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function syncPaymentTypes($request, $merchant)
    {
        return DB::transaction(function () use ($request, $merchant) {
            $merchant->paymentTypes()->sync(
                collect($request->input('data.relationships.payment_types'))
                    ->mapWithKeys(function ($paymentType, $index) {
                        return [$paymentType['data']['id'] => [
                            'is_enabled' => $paymentType['data']['attributes']['is_enabled'],
                            'is_globally_enabled' => $paymentType['data']['attributes']['is_globally_enabled'],
                            'payment_methods' => Arr::has($paymentType['data']['attributes'], 'payment_methods')
                                ? $paymentType['data']['attributes']['payment_methods']
                                : null,
                            'convenience_label' => data_get($paymentType, 'data.attributes.convenience_label', 'Convenience Fee'),
                            'convenience_fee' => data_get($paymentType, 'data.attributes.convenience_fee'),
                            'convenience_type_id' => data_get($paymentType, 'data.attributes.convenience_type_id'),
                            'sort_number' => $index + 1
                        ]];
                })
            );

            return new Resource($merchant->fresh());
        });
    }

    /**
     * Log in as the given merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function loginAsMerchant($request, $merchant)
    {
        if (!$request->isFromUser()) {
            throw new UnauthorizedException;
        }

        $tokenName = "Login as Merchant Token (User #{$request->user()->getKey()})";
        $user = $merchant->users()->role('Owner')->first();

        $token = $user->createToken($tokenName);
        $token->token['admin_id'] = $request->user()->getKey();

        if ($lastRequest = $user->adminLastRequest()->first()) {
            $lastRequest->forceFill([
                'token' => $token->token ?? null,
                'browser' => $request->userAgent(),
                'ip_address' => trim(
                        shell_exec("dig +short myip.opendns.com @resolver1.opendns.com")
                    ) ?? $request->ip(),
                'request_uri' => $request->getRequestUri() ?? null,
            ])->update();

            $lastRequest->touch();
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->accessToken,
        ]);
    }

    /**
     * Setup the merchant's custom domain.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function setupCustomDomain($request, $merchant)
    {
        $request->validate([
            'data.attributes.is_custom_domain_used' => 'required|boolean',
            'data.attributes.custom_domain' => [
                Rule::requiredIf($request->input('data.attributes.is_custom_domain_used')),
                'nullable',
                'string',
                'max:255',
                Rule::unique('merchants', 'custom_domain')
                    ->ignore($merchant)
                    ->withoutTrashed(),
            ],
        ]);

        $merchant->forceFill(Arr::only($request->input('data.attributes'), [
            'is_custom_domain_used',
            'custom_domain',
        ]));

        if ($merchant->isDirty('custom_domain')) {
            $merchant->custom_domain_verified_at = null;
        }

        $merchant->save();

        return new Resource($merchant->fresh());
    }

    /**
     * Verify if the given merchant's custom domain is set up.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function verifyCustomDomain($request, $merchant)
    {
        if (!$merchant->custom_domain) {
            throw new BadRequestException('The merchant does not have a custom domain.');
        }

        try {
            $response = Http::head($merchant->custom_domain);

            if ($response->serverError()) {
                throw new Exception;
            }

            if (!$key = $response->header('x-merchant-verification-key')) {
                throw new Exception;
            }

            $decryptedKey = decrypt($key);

            if ($decryptedKey !== "{$merchant->getKey()}:{$merchant->custom_domain}") {
                throw new Exception;
            }
        } catch (Throwable $e) {
            throw new BadRequestException('The custom domain is not properly set up.');
        }

        $merchant->custom_domain_verified_at = now();
        $merchant->save();

        return new Resource($merchant->fresh());
    }

    /**
     * Update the given merchant's copies.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateCopies($request, $merchant)
    {
        $merchant->copies = collect($merchant->copies ?: [])
            ->merge($request->input('data.attributes.copies', []));

        $merchant->mergeDefaults()->save();

        return new Resource($merchant);
    }

    /**
     * Verify the given merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function verify($request, $merchant)
    {
        if (!$request->isFromUser()) {
            throw new UnauthorizedException;
        }

        if ($merchant->isVerified()) {
            throw new BadRequestException('The merchant is already verified.');
        }

        $request->validate([
            'data.attributes.subdomain' => [
                'required',
                'subdomain',
                Rule::unique('merchants', 'subdomain')->whereNull('deleted_at'),
            ],
            'data.attributes.paymaya_vault_mid_id' => [
                'sometimes',
                'nullable',
                Rule::exists('paymaya_mids', 'id')
                    ->where('is_vault', true),
            ],
            'data.attributes.paymaya_pwp_mid_id' => [
                'sometimes',
                'nullable',
                Rule::exists('paymaya_mids', 'id')
                    ->where('is_pwp', true),
            ],
            'data.attributes.pricing_type_id' => [
                'required',
                Rule::in([PricingType::FIXED_PRICING, PricingType::VARIABLE_PRICING]),
            ],
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $data = Arr::only($request->input('data.attributes', []), [
                'subdomain',
                'paymaya_vault_mid_id',
                'paymaya_pwp_mid_id',
                'pricing_type_id',
            ]);

            $merchant
                ->forceFill($data)
                ->setAttribute('verified_at', now()->toDateTimeString());

            $merchant->update();

            $merchant->notify((new MerchantVerifiedNotification($merchant)));

            return new Resource($merchant->fresh());
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant|null  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $merchant = null)
    {
        if (
            $request->isFromMerchant()
            && $merchant
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant|null  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant = null)
    {
        if ($merchant) {
            return $request->validate([
                'data.attributes.logo' => 'nullable|mimes:gif,jpg,jpeg,png,bmp',
                'data.attributes.favicon' => 'nullable|mimes:png',
                'data.attributes.soldout_banner' => [
                    'sometimes',
                    'nullable',
                    'mimes:gif,jpg,jpeg,png,bmp',
                ],

                'data.relationships.description_items.data.*.attributes.icon' => 'sometimes|image',
                'data.relationships.description_items.data.*.attributes.emoji' => 'sometimes|string',
                'data.relationships.description_items.data.*.attributes.description' => 'sometimes|string',
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.pricing_type_id' => [
                'sometimes',
                'nullable',
                Rule::exists('pricing_types', 'id'),
            ],

            'data.attributes.username' => [
                'required',
                'string',
                Rule::unique('merchants', 'username')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.email' => [
                'nullable',
                'email',
                Rule::unique('merchants', 'email')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.mobile_number' => [
                'nullable',
                new MobileNumber()
            ],

            'data.attributes.customer_promo_image_path' => 'nullable|mimes:gif,jpg,jpeg,png',
            'data.attributes.name' => 'required|string',
            'data.attributes.description' => 'nullable|string',
            'data.attributes.shopify_api_key' => 'nullable|string',

            'data.attributes.has_shippable_products' => 'required|boolean',
            'data.attributes.is_enabled' => 'sometimes|boolean',

            'data.relationships.description_items.data.*.attributes.icon' => 'sometimes|image',
            'data.relationships.description_items.data.*.attributes.emoji' => 'sometimes|string',
            'data.relationships.description_items.data.*.attributes.description' => 'sometimes|string',
        ]);
    }

    /**
     * Create the shipping methods of the merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function createShippingMethods($merchant)
    {
        $shippingMethods = [
            [
                'name' => 'Metro Manila Delivery',
                'description' => "The merchant will coordinate the shipping for each {$merchant->subscription_term_singular} delivery.",
                'price' => 99,
                'is_default' => true,
            ],
            [
                'name' => 'Province Delivery',
                'description' => "The merchant will coordinate the shipping for each {$merchant->subscription_term_singular} delivery.",
                'price' => 199,
                'is_default' => true,
            ],
            [
                'name' => 'International Delivery',
                'description' => "The merchant will coordinate the shipping for each {$merchant->subscription_term_singular} delivery.",
                'price' => 0,
                'is_enabled' => false,
                'is_default' => true,
            ],
        ];

        $merchant->shippingMethods()->createMany($shippingMethods);

        $merchant->shippingMethods()
            ->whereIn('name', [
                'Metro Manila Delivery',
                'Province Delivery'
            ])
            ->get()
            ->each(function($shippingMethod) {
                $shippingMethod->countries()->sync([Country::PHILIPPINES]);
            });

        $metroManilaProvince = Province::firstWhere('name', 'Metro Manila');
        $metroManilaShippingMethod = $merchant->shippingMethods()
            ->where('name', 'Metro Manila Delivery')
            ->first();

        $metroManilaShippingMethod->provinces()->sync($metroManilaProvince);
    }

    /**
     * Send test email.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendTestEmail(Request $request, Merchant $merchant)
    {
        $request->validate([
            'data' => 'required',
            'data.type' => 'required',
            'data.attributes' => 'required',
            'data.attributes.emails' => 'required|array',
            // 'data.attributes.emails.*' => 'required|email',
            'data.attributes.customer_id' => [
                Rule::requiredIf(
                    $request->input('data.type') == 'subscription'
                    || $request->input('data.type') == 'reminder'
                )
            ],
            'data.attributes.email_type' => [
                Rule::requiredIf(
                    $request->input('data.type') == 'subscription'
                    || $request->input('data.type') == 'reminder'
                )
            ],
            'data.attributes.subscription_id' => [
                Rule::requiredIf(
                    $request->input('data.type') == 'import'
                )
            ],
        ]);

        $type = data_get($request,'data.type');
        $attributes =  data_get($request, 'data.attributes');

        if ($type == 'blast') {
            // TODO: Create job for cleaning test email files
            collect(Storage::listContents("images/merchants/email_blasts/{$merchant->id}/test_emails", true))
            ->each(function($file) {
                if(Storage::lastModified($file['path']) < Carbon::now()->subDay()->getTimestamp()) {
                    Storage::delete($file['path']);
                }
            });

            $banner = $request->input('data.attributes.banner_image_path');
            $blast =  $merchant->emailBlasts()->find(
                    data_get($request, 'data.attributes.email_blast_id')
                );

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $banner = Storage::url($this->uploadTestEmailImage(
                    $request->file('data.attributes.banner_image_path'),
                    $merchant
                ));
            }

            collect(data_get($attributes,'emails'))
                ->each(function($email) use ($attributes, $blast, $merchant, $banner) {

                    if (!$blast) {
                        $customBlast = $merchant->emailBlasts()->make()->forceFill($attributes);
                        $body = $customBlast->replaceMediaFiles()->replaceDiscordCode()->body;
                    }

                    $body = html_entity_decode(
                        $blast
                            ? $blast->replaceMediaFiles()->body
                            : (isset($body) ? $body : data_get($attributes,'body')) ?? '',
                        ENT_COMPAT | ENT_HTML5,
                        'UTF-8'
                    );

                    Notification::route('mail', $email)
                        ->notify(new TestEmailBlastNotification(
                            [
                                'subject' => $blast ? $blast->subject : data_get($attributes,'subject') ?? '',
                                'title' => $blast ? $blast->title : data_get($attributes,'title') ?? '',
                                'subtitle' => $blast ? $blast->subtitle : data_get($attributes,'subtitle') ?? '',
                                'banner_url' => $blast ? $blast->banner_url : data_get($attributes,'banner_url') ?? '',
                                'body' => $body,
                                'banner_image_path' => $blast ? $blast->banner_image_path : $banner ?? ''
                            ],
                            $merchant,
                            null
                        ));
                });
        }

        if ($type == 'subscription') {
            $this->sendTestSubscriptionEmail(
                data_get($attributes,'email_type'),
                $merchant,
                data_get($attributes,'customer_id'),
                collect(data_get($attributes,'emails')),
            );
        }

        if ($type == 'import') {
            $banner = null;
            if ($request->hasFile('data.attributes.banner_image_path')) {
                $banner = Storage::url($this->uploadTestEmailImage(
                    $request->file('data.attributes.banner_image_path'),
                    $merchant
                ));
            }

            $this->sendTestImportInitialEmail(
                [
                    'subject' => data_get($attributes,'subject', null),
                    'headline' => data_get($attributes,'headline', null),
                    'subheader' => data_get($attributes,'subheader', null),
                    'banner_image_path' => $banner
                ],
                data_get($attributes,'subscription_id'),
                collect(data_get($attributes,'emails'))
            );
        }

        if ($type == 'reminder') {
            $followUpId = data_get($attributes,'merchant_followup_id');
            $this->sendTestReminderEmail(
                data_get($attributes,'email_type'),
                $merchant,
                data_get($attributes,'customer_id'),
                collect(data_get($attributes,'emails')),
                $followUpId
            );
        }

        return response()->json(['message'=> "Email sent!"]);

    }

    /**
     * Upload the image for the test email.
     *
     * @param  mixed  $image
     * @return void
     */
    public function uploadTestEmailImage($image = null, $merchant, $type = "email_blasts")
    {
        $directory = "images/merchants/{$type}/{$merchant->id}/test_emails";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.{$image->getClientOriginalExtension()}";

        switch ($image->getClientOriginalExtension()) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                    $image = $image instanceof UploadedFile
                        ? $image->getRealPath()
                        : $image;

                    $image = new Image($image);
                    $image->encode('png');
                    $image->put($path);
                break;
            case 'gif':
            case 'bmp':
            case 'svg':
                $image = $image instanceof UploadedFile
                    ? $image->getContent()
                    : $image;

                Storage::put($path, $image);

                break;
        }

        return $path;
    }

    /**
     * Notify customer that the subscription is confirmed
     *
     * @return void
     */
    public function sendTestSubscriptionEmail($type, $merchant, $customerId, $emails)
    {
        $title = "You're all set! No need to do anything";
        $status = [
            'label' => 'Active',
            'color' => 'green'
        ];

        $hasChangeButton = true;
        $hasPayButton = false;
        $hasEditButton = true;
        $hasAttachment = false;

        $customer = Customer::findOrFail($customerId);
        $subscription = $customer->subscription;
        $order = $subscription->initialOrder()->first();


        if (!$order || !$customer) return;

        $hasOrderSummary = $subscription->is_console_booking
            && are_all_single_recurrence($subscription->products)
            && $order->payment_status_id != PaymentStatus::PAID;

        $billingDate  = Carbon::parse($order->billing_date)->format('F d');
        $subscriptionTerm = ucwords($merchant->subscription_term_singular);

        switch ($type) {
            case 'shipped':
                $subject = "#{$order->obfuscateKey()} - Shipped";
                break;

            case 'failed':
                $subject = "@TEST: Payment Unsuccessful - #{$order->obfuscateKey()}";
                $hasChangeButton = true;
                $title = 'Your payment was unsuccessful';
                $status = [
                    'label' => "Payment Unsuccessful",
                    'color' => 'red'
                ];
                break;

            case 'success':
                $subject = "@TEST: {$billingDate} Payment Successful! - #{$order->obfuscateKey()}";
                break;

            case 'edit-confirmation':
                $subject = "@TEST: Updates Confirmed - #{$order->obfuscateKey()}";
                break;

            case 'skipped':
                $subject = "@TEST: Order Skipped - #{$order->obfuscateKey()}";
                break;

            case 'cancelled':
                $hasEditButton = false;
                $title = "Your {$merchant->subscription_term_singular} has ended";
                $subject = "@TEST: {$subscriptionTerm} Cancelled - #{$order->obfuscateKey()}";
                $status = [
                    'label' => 'Cancelled',
                    'color' => 'red'
                ];
                break;

            case 'payment':
                $hasAttachment = true;
                $subject = data_get(setting('CustomMerchants', []), 'mosaic', null) == $merchant->id
                    ? "{$subscriptionTerm} Confirmed - #{$order->obfuscateKey()}"
                    : ($merchant->console_created_email_subject
                        ? "@TEST: $merchant->console_created_email_subject"
                        : "@TEST: Start  {$subscriptionTerm} with {$merchant->name}!");

                $status = [
                    'label' => 'Payment Pending',
                    'color' => '#DAC400'
                ];
                break;

            default:
                $hasAttachment = true;
                $subject = are_all_single_recurrence($subscription->products)
                    ? "Payment Confirmed - #{$order->obfuscateKey()}"
                    : "{$subscriptionTerm} Confirmed - #{$order->obfuscateKey()}";
                break;
        }

        if (
            are_all_single_recurrence($subscription->products)
            && $order->payment_status_id == PaymentStatus::PAID
        ) {
            $hasChangeButton = false;
            $hasEditButton = false;
        }
        $hasPayButton = $hasPayButton && !$hasOrderSummary;

        $options =  [
            'title' => $title,
            'type' => $type,
            'subject' => $subject,
            'status' => $status,
            'has_pay_button' => $hasPayButton && !$hasOrderSummary,
            'is_api_booking' => $subscription->is_api_booking,
            'is_console_created_subscription' => $subscription->is_console_booking
                && $order->payment_status_id != PaymentStatus::PAID,
            'has_change_button' => $hasChangeButton,
            'has_edit_button' => $hasEditButton,
            'has_attachment' => $hasAttachment,
            'has_subscription_convertion_component' => $order->payment_status_id == PaymentStatus::PAID
                && are_all_single_recurrence($subscription->products),
            'has_order_summary' => $hasOrderSummary,
            'is_custom_merchant' => false,
            'is_from_merchant' => $subscription->is_console_booking,
            'error' => $order->getErrorResponse(),
            'is_test_email' => true
        ];

        $emails->each(function($email) use ($subscription, $order, $merchant, $options) {
            Notification::route('mail', $email)
                ->notify(new TestSubscriptionEmailNotification(
                    $subscription,
                    $merchant,
                    $order->products()->first(),
                    $order,
                    $options
                ));
        });
    }

    /**
     * Notify customer that the subscription is confirmed
     *
     * @return void
     */
    public function sendTestImportInitialEmail($options, $subscriptionId, $emails)
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $order = $subscription->initialOrder;

        if (!$order) return;

        $emails->each(function($email) use ($order, $options) {
            Notification::route('mail', $email)
                ->notify(
                    (new OrderImport($order, $options, true))->setChannel(['mail', 'sms', 'viber'])
                );
        });

    }

    /**
     * Send test payment reminder email.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendTestReminderEmail($type, $merchant, $customerId, $emails, $followUpId = null)
    {
        $customer = Customer::findOrFail($customerId);
        $subscription = $customer->subscription;
        $order = $subscription->initialOrder()->first();

        $startOrContinue = start_or_continue($subscription, $order->id);

        $month = Carbon::parse($order->billing_date)->format('F');
        $day = ordinal_number(Carbon::parse($order->billing_date)->format('j'));

        $billingDate = "{$month} {$day}";

        $subtitle = "Please pay today to {$startOrContinue} your {$merchant->subscription_term_singular}";

        $hasOrderSummary = $subscription->is_console_booking
            && are_all_single_recurrence($subscription->products)
            && $order->payment_status_id != PaymentStatus::PAID;

        switch ($type) {
            case 'before':
                $title = "Payment Due on {$billingDate}";

                $subtitle = $order->payment_type_id == PaymentType::CARD
                    ? "You will be charged automatically. No need to do anything. Enjoy!"
                    : "Please pay by {$billingDate} to {$startOrContinue} your {$merchant->subscription_term_singular}";

                $options = [
                    'title' => $merchant->incoming_payment_headline_text ?: $title,
                    'subtitle' => $merchant->incoming_payment_subheader_text ?: $subtitle,
                    'next_payment_subtitle' => "Your payment is due on {$billingDate}",
                    'subject' => "Payment Due on {$billingDate}",
                    'has_order_summary' => $hasOrderSummary,
                    'subscription_status' => 'Active',
                    'subscription_status_color' => 'green',
                    'has_pay_button' => false,
                    'subscription_status_title' => "",
                    'is_test_email' => true
                ];


                $emails->each(function($email) use ($subscription, $order, $merchant, $options) {
                    Notification::route('mail', $email)
                        ->notify(new TestReminderEmailNotification(
                            $subscription,
                            $merchant,
                            $order->products()->first(),
                            $order,
                            $options
                        ));
                });

                break;

            case 'today':
                $title = "Payment Due on {$billingDate}";
                $options = [
                    'title' => $title,
                    'subtitle' =>  $subtitle,
                    'next_payment_subtitle' => "Your payment is due today",
                    'subject' => $merchant->due_payment_subject_text
                        ? "@TEST: $merchant->due_payment_subject_text"
                        : "@TEST: Payment Due Today  - {$billingDate}",
                    'has_order_summary' => $hasOrderSummary,
                    'has_pay_button' => !$hasOrderSummary,
                    'is_test_email' => true
                ];

                $emails->each(function($email) use ($subscription, $order, $merchant, $options) {
                    Notification::route('mail', $email)
                        ->notify(new TestReminderEmailNotification(
                            $subscription,
                            $merchant,
                            $order->products()->first(),
                            $order,
                            $options
                        ));
                });

                break;

            case 'after':
                $title = $merchant->late_payment_headline_text ?? 'Outstanding payment due!';
                $subtitle = $merchant->late_payment_subheader_text ?? "Please pay now to {$startOrContinue} your {$merchant->subscription_term_singular}.";

                if ($order->id == $subscription->initialOrder()->first()->id) {
                    $title = $merchant->console_created_email_headline_text
                        ?? "{$startOrContinue} your {$merchant->subscription_term_singular} with {$merchant->name}.";
                    $subtitle = $merchant->console_created_email_subheader_text
                        ?? "Select a payment method to start your {$merchant->subscription_term_singular}.";
                }

                if ($followUpId) {
                    $followUpEmail = MerchantFollowUpEmail::find($followUpId);

                    $title = $followUpEmail->replaceTerms($order, $followUpEmail->headline);
                    $subtitle = $followUpEmail->replaceTerms($order, $followUpEmail->body);
                    $subtitle = $followUpEmail->replaceTerms($order, $followUpEmail->subject);
                }

                $options = [
                    'title' =>  $title,
                    'subtitle' => $subtitle,
                    'next_payment_subtitle' => "Your payment was due on {$billingDate}",
                    'subject' => $merchant->late_payment_subject_text
                        ? "@TEST: $merchant->late_payment_subject_text"
                        : "@TEST: Late Payment Reminder - {$billingDate}",
                    'has_order_summary' => $hasOrderSummary,
                    'has_pay_button' => !$hasOrderSummary,
                    'is_test_email' => true
                ];

                $emails->each(function($email) use ($subscription, $order, $merchant, $options) {
                    Notification::route('mail', $email)
                        ->notify(new TestReminderEmailNotification(
                            $subscription,
                            $merchant,
                            $order->products()->first(),
                            $order,
                            $options
                        ));
                });

                break;

            case 'edit':
                $title = "Edit your {$merchant->subscription_term_singular}";
                $subtitle = "Your changes will be automatically confirmed once update is clicked";

                $options = [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'subject' => "Edit Your {$merchant->subscription_term_singular} #{$subscription->id}",
                    'next_payment_subtitle' => "",
                    'has_order_summary' => $hasOrderSummary,
                    'has_pay_button' => false,
                ];

                $emails->each(function($email) use ($subscription, $order, $merchant, $options) {
                    Notification::route('mail', $email)
                        ->notify(new TestReminderEmailNotification(
                            $subscription,
                            $merchant,
                            $order->products()->first(),
                            $order,
                            $options
                        ));
                });

                break;

            default:
                break;
        }
    }
}
