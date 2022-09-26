<?php

namespace App\Observers;

use App\Facades\Cloudflare\Zone;
use App\Facades\Viber;
use App\Libraries\Shopify\Rest\Webhook as ShopifyWebhook;
use App\Libraries\Viber\Message as ViberMessage;
use App\Libraries\Viber\Webhook as ViberWebhook;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\OrderNotification;
use App\Models\PricingType;
use App\Models\Product;
use App\Notifications\CustomDomainSetup;
use App\Notifications\MerchantMaxAmountLimitNotification;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

class MerchantObserver
{
    /**
     * Handle the merchant "creating" event.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function creating($merchant)
    {
        $this->setDefaults($merchant);
        $this->setPayMayaKeys($merchant);
        $this->setFormattedMobileNumber($merchant);
    }

    /**
     * Handle the merchant "created" event.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function created($merchant)
    {
        $this->createRecurrences($merchant);
        $this->createPaymentTypes($merchant);
        $this->createOrderNotifications($merchant);
        $this->createComponents($merchant);
    }

    /**
     * Handle the merchant "updating" event.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function updating($merchant)
    {
        $this->setPayMayaKeys($merchant);
        $this->setFormattedMobileNumber($merchant);
    }

    /**
     * Handle the merchant "updated" event.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function updated($merchant)
    {
        $this->cascadeShippableStatus($merchant);
        $this->createDnsRecord($merchant);
        $this->syncOwnerStatus($merchant);
        $this->resetMaximumAmountLimit($merchant);
        $this->notifyMaxAmountLimitToMerchant($merchant);
        $this->registerShopifyWebhook($merchant);
        $this->registerViberWebhook($merchant);
        $this->sendCustomDomainSetupEmail($merchant);
        $this->sendWelcomeMessage($merchant);
        $this->generateDeeplinks($merchant);
    }

    /**
     * Create merchant-specific order notifications.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function createOrderNotifications(Merchant $merchant)
    {
        OrderNotification::query()
            ->whereNull('merchant_id')
            ->get()
            ->each(function (OrderNotification $notification) use ($merchant) {
                $merchant->orderNotifications()
                    ->make()
                    ->fill(Arr::except($notification->toArray(), ['id', 'merchant_id']))
                    ->save();
            });
    }


    /**
     * Create components
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function createComponents($merchant)
    {
        $defaultComponent = [
            'Customer Details' => [
                'is_default' => true,
                'is_visible' => true,
                'is_customer_details' => true,
                'custom_fields' => [
                    'Name' => [
                        'sort_number' => 1,
                        'code' => 'name',
                        'is_default' => true,
                        'data_type' => 'string',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'Email Address' => [
                        'sort_number' => 2,
                        'code' => 'emailAddress',
                        'is_default' => true,
                        'data_type' => 'string',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'Mobile Number' => [
                        'sort_number' => 3,
                        'code' => 'mobileNumber',
                        'is_default' => true,
                        'data_type' => 'number',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                ]
            ],
            'Address & Shipping Details' => [
                'is_default' => true,
                'is_visible' => true,
                'is_address_details' => true,
                'custom_fields' => [
                    'Country' => [
                        'sort_number' => 1,
                        'code' => 'country',
                        'is_default' => true,
                        'data_type' => 'dropdown',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'Street Address / Building Name' => [
                        'sort_number' => 2,
                        'code' => 'address',
                        'is_default' => true,
                        'data_type' => 'string',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'City' => [
                        'sort_number' => 3,
                        'code' => 'city',
                        'is_default' => true,
                        'data_type' => 'string',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'Province' => [
                        'sort_number' => 4,
                        'code' => 'province',
                        'is_default' => true,
                        'data_type' => 'dropdown',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'Barangay' => [
                        'sort_number' => 5,
                        'code' => 'barangay',
                        'is_default' => true,
                        'data_type' => 'string',
                        'is_required' => true,
                        'is_visible' => true,
                    ],
                    'Zip Code' => [
                        'sort_number' => 6,
                        'code' => 'zipCode',
                        'is_default' => true,
                        'data_type' => 'string',
                        'is_required' => true,
                        'is_visible' => true,
                    ]
                ]
            ]
        ];

        collect($defaultComponent)
            ->each(function ($component, $key) use($merchant) {
                $customComponent = $merchant->customComponents()
                    ->make(
                        Arr::except($component, 'custom_fields') + [
                            'title' => $key
                        ]
                    );

                if (!$merchant->has_shippable_products && data_get($component, 'is_address_details')) {
                    $customComponent->title = 'Address & Billing Details';
                }

                $customComponent->sort_number = $merchant->customComponents()->max('sort_number') + 1;
                $customComponent->save();

                collect($component['custom_fields'] ?? [])
                    ->each(function ($customField, $key) use($customComponent, $merchant) {
                        $customField = $customComponent->customFields()
                            ->make($customField + [
                                'merchant_id' => $merchant->id,
                                'label' => $key
                            ]);

                        $customField->save();
                    });
            });
    }

    /**
     * Update the formatted mobile number
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function setFormattedMobileNumber($merchant)
    {
        if (
            !$merchant->isDirty(['mobile_number', 'country_id'])
        ) return;

        $country = $merchant->country()->first();

        $merchant->formatted_mobile_number = $country
            ? "{$country->dial_code}{$merchant->mobile_number}"
            : $merchant->mobile_number;
    }

      /**
     * Post to merchant webhooks about MERCHANT updates.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function sendWelcomeMessage($merchant)
    {
        if ($merchant->wasChanged('viber_info') && $merchant->viber_info) {
            Viber::withToken(
                config('services.viber.merchant_auth_token'),
                function () use($merchant) {
                    return ViberMessage::send(
                        $merchant->viber_info['id'],
                        "Thank you for subscribing to HelixPay Merchants! You will now receive real time notifications on your {$merchant->subscription_term_plural} through Viber."
                    );
                }
            );
        }
    }

    /**
     * Send the custom domain setup instructions to the owner.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function sendCustomDomainSetupEmail($merchant)
    {
        if ($merchant->wasChanged('custom_domain') && $merchant->custom_domain) {
            $merchant->owner->notify(new CustomDomainSetup($merchant));
        }
    }

    /**
     * Cascade the shippable status to the merchant's products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function registerViberWebhook($merchant)
    {
        if (!$merchant->wasChanged(['viber_key', 'viber_uri'])) return;

        if ($merchant->viber_key && $merchant->viber_uri) {
            Viber::setViberCredentials(
                $merchant->viber_key,
                $merchant->name,
                $merchant->logo_image_path
            );

            ViberWebhook::setup(env('APP_URL').'/v1/viber');
        }
    }

    /**
     * Register update products and app unsintall webhook to shopify
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function registerShopifyWebhook($merchant)
    {
        if (!$merchant->wasChanged('shopify_info')) return;

        if ($merchant->shopify_info) {
            $token = isset($merchant->shopify_info['accessToken'])
                ? $merchant->shopify_info['accessToken']
                : $merchant->shopify_info['access_token'];

            $topics = collect([
                'products/update',
                'products/delete',
                'products/create',
                'app/uninstalled',
                'collections/update',
                'collections/delete'
            ]);

            $topics->each(function ($topic) use ($merchant, $token) {
                (new ShopifyWebhook($merchant->shopify_domain, $token))
                    ->create([
                        'topic' => $topic,
                        'address' => env('APP_URL').'/v1/shopify/webhooks',
                        'format' => 'json',
                        'metafield_namespaces' => [
                            'bukopay'
                        ]
                    ])->then(function () {
                    })
                    ->wait(false);
            });
        }
    }


    /**
     * Cascade the shippable status to the merchant's products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function cascadeShippableStatus($merchant)
    {
        if (!$merchant->wasChanged('has_shippable_products')) {
            return;
        }

        $merchant->products()->get()->each(function (Product $product) use ($merchant) {
            $product->update(['is_shippable' => $merchant->has_shippable_products]);
        });
    }

    /**
     * Reset maximum amount limit of merchant
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function resetMaximumAmountLimit($merchant)
    {
        if ($merchant->wasChanged('is_enabled') && $merchant->is_enabled) {
            $merchant->hourly_total_amount_paid = null;
            $merchant->has_reached_max_amount = false;
            $merchant->saveQuietly();
        }
    }

    /**
     * Notify merchant that maximum amount limit is reached.
     *      *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function notifyMaxAmountLimitToMerchant($merchant)
    {
        if ($merchant->wasChanged('has_reached_max_amount')) {
            if ($merchant->has_reached_max_amount) {
                $merchant->notify((new MerchantMaxAmountLimitNotification($merchant)));
            } else {
                $merchant->hourly_total_amount_paid = null;
                $merchant->saveQuietly();
            }
        }
    }

    /**
     * Set the defaults for the merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function setDefaults($merchant)
    {
        $merchant->single_recurrence_title = 'Try a Subscription?';
        $merchant->single_recurrence_subtitle = 'You can easily change your order to a subscription!';
        $merchant->single_recurrence_button_text = 'Create a Subscription';
        $merchant->members_login_text = '<p>If you have a membership, we will send you a 6 digit OTP code to get access.</p><br><p>Don&apos;t have access yet? Buy a membership to gain access.</p>';
        $merchant->members_page_text_color = '#2F2F2F';

        if (!$merchant->has_shippable_products) {
            $merchant->are_orders_skippable = false;
        }

        if (!$merchant->pricing_type_id) {
            $merchant->pricing_type_id = PricingType::FIXED_PRICING;
        }

        if (!$merchant->free_delivery_text) {
            $merchant->free_delivery_text = "Free shipping on subscription orders over XXXX";
        }

        if (!$merchant->faqs_title) {
            $merchant->faqs_title = 'Learn more how subscriptions work';
        }

        if (!$merchant->faqs) {
            $merchant->faqs = "
            <h1><b>How do subscriptions work?</b></h1> <p>Subscriptions ensure you never run
            out and don't have to worry about remembering to order. With a subscription,
            monthly payments and deliveries are automatic so it's super easy.</p><h1><b>How
            will I be able to manage my subscription?</b></h1> <p>You'll be able to edit
            the payment method and change the inclusions of your order on the email you'll
            receive every month.</p><h1><b>When will I receive my order for the month?</b></h1>
            <p>Deliveries will be processed soon after your payment has been made each month.
            The shipping costs are already included in your billing summary.</p><h1><b>What
            happens when I am not at home to receive my package but it's been paid for
            already?</b></h1> <p>We work with top courier services to ensure that your
            products arrive safely at your door. Drivers normally give you a call when
            they are on their way.</p><h1><b>How can I pay?</b></h1> <p>For credit/debit
            card payments, your account will automatically get charged each month and you
            won't have to do anything. Bank transfer will be processed once you
            proceed with the payment flow each month. You will get an email 3 days before you
            will be charged for card payments to notify you of the billing summary while you will
            get an email reminder to proceed with payment for bank transfers.</p><h1><b>Can I trust
            you with my card details?</b></h1> <p>Your payment details are encrypted and stored by top
            payment technology providers. We do not have access to any of your payment details. The only
            information we have is the payment method you've selected.</p><h1><b>Where can I contact you
            if I have concerns about my subscription?</b></h1> <p>Send us a message on our
            <a href='https://www.facebook.com/BukoPay.ph/' target='_blank'>Facebook</a>
            page and we'll respond as soon as we can.</p>";
        }

        $fontSettings = [
            'storefront_description' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'storefront_description_items' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'membership_description' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'membership_description_items' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'product_title' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'product_body' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'product_price' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'product_group_tab' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
            'faq_link' => [
                'font-family' => null,
                'font-size' => null,
                'font-weight' => null
            ],
        ];

        $buttonSettings = [
            'pay_button' => [
                'label' => 'Pay Now',
                'css' => [
                    'background-color' => null
                ]
            ],
            'recurring_button' => [
                'label' => 'Start Subscription',
                'css' => [
                    'background-color' => null
                ]
            ],
            'product_details_button' => [
                'label' => 'Details',
                'css' => [
                    'background-color' => null
                ]
            ],
            'product_select_button' => [
                'label' => 'Select',
                'css' => [
                    'background-color' => null
                ]
            ],
            'checkout_button' => [
                'label' => 'Checkout',
                'css' => [
                    'background-color' => null
                ]
            ],
            'add_to_order_button' => [
                'label' => 'Add to Order',
                'css' => [
                    'background-color' => null
                ]
            ],
            'members_page_button' => [
                'label' => 'Members Page',
                'css' => [
                    'background-color' => null
                ]
            ],
        ];


        $merchant->font_settings = $fontSettings;
        $merchant->buttons = $buttonSettings;

        $merchant->mergeDefaults();
    }

    /**
     * Generate deep links for existing products that are created before verified
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function generateDeeplinks($merchant)
    {
        $wasVerified = $merchant->wasChanged('verified_at')
            && is_null($merchant->getOriginal('verified_at'));

        if ($wasVerified) {
            $merchant->products()->get()->each(function (Product $product) use ($merchant) {
                $scheme = app()->isLocal() ? 'http' : 'https';
                $slug = $product->slug ?? $product->setSlug($product->title);
                $summaryUrl = config('bukopay.url.deep_link_summary');

                $url = "{$scheme}://{$merchant->subdomain}.{$summaryUrl}?product={$slug}";

                $deepLinkUrl = $product->deepLinks()->make()->generateShortUrl($url);
                $deepLinkUrl->save();

                $product->deep_link = $deepLinkUrl->deep_link;
                $product->saveQuietly();
            });
        }
    }

    /**
     * Create a DNS record fo the merchant's booking site.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function createDnsRecord($merchant)
    {
        if (app()->isLocal()) {
            return;
        }

        $wasVerified = $merchant->wasChanged('verified_at')
            && is_null($merchant->getOriginal('verified_at'));

        $wasSubdomainSet = $merchant->wasChanged('subdomain')
            && is_null($merchant->getOriginal('subdomain'));

        if ($merchant->subdomain && $wasVerified && $merchant->verified_at) {
            if (!app()->isProduction() && $wasSubdomainSet) {
                $merchant->subdomain = subdomain($merchant->subdomain);
                $merchant->saveQuietly();
            }

            try {
                Zone::createDnsRecord($merchant->subdomain, config('bukopay.ip.booking_site'));
            } catch (RequestException $e) {
                if (!count($errors = $e->response->json('errors'))) {
                    throw $e;
                }

                $code = Arr::first($errors)['code'] ?? null;
                if ($code === Zone::DNS_RECORD_EXISTS) return;

                throw $e;
            }
        }
    }

    /**
     * Sync the merchant's status with the owner.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function syncOwnerStatus($merchant)
    {
        if (!$merchant->wasChanged('is_enabled')) {
            return;
        }

        if ($owner = $merchant->owner()->first()) {
            $owner->update($merchant->only('is_enabled'));
        }
    }

    /**
     * Set the PayMaya keys if MIDs are set.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function setPayMayaKeys($merchant)
    {
        $wasChanged = function ($key) use ($merchant) {
            return (!$merchant->exists)
                || ($merchant->exists && $merchant->isDirty($key));
        };

        if ($wasChanged('paymaya_vault_mid_id')) {
            $mid = $merchant->paymayaVaultMid()->first();

            $merchant->forceFill([
                'paymaya_vault_public_key' => optional($mid)->getRawOriginal('public_key'),
                'paymaya_vault_secret_key' => optional($mid)->getRawOriginal('secret_key'),
            ]);
        }

        if ($wasChanged('paymaya_pwp_mid_id')) {
            $mid = $merchant->paymayaPwpMid()->first();

            $merchant->forceFill([
                'paymaya_pwp_public_key' => optional($mid)->getRawOriginal('public_key'),
                'paymaya_pwp_secret_key' => optional($mid)->getRawOriginal('secret_key'),
            ]);
        }

        if ($wasChanged('paymaya_vault_mid_console_id')) {
            $mid = $merchant->paymayaVaultConsoleMid()->first();

            $merchant->forceFill([
                'paymaya_vault_console_public_key' => optional($mid)->getRawOriginal('public_key'),
                'paymaya_vault_console_secret_key' => optional($mid)->getRawOriginal('secret_key'),
            ]);
        }

        if ($wasChanged('paymaya_pwp_mid_console_id')) {
            $mid = $merchant->paymayaPwpConsoleMid()->first();

            $merchant->forceFill([
                'paymaya_pwp_console_public_key' => optional($mid)->getRawOriginal('public_key'),
                'paymaya_pwp_console_secret_key' => optional($mid)->getRawOriginal('secret_key'),
            ]);
        }
    }

    /**
     * Create payment types
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function createPaymentTypes($merchant)
    {
        $paymentTypes = [
            [
                'id' => 3,
                'name' => 'Credit/Debit Card',
                'is_enabled' => setting('IsCcPaymentEnabled', true),
                'is_globally_enabled' => setting('IsCcPaymentEnabled', true),
                'sort_number' => 1,
                'convenience_label' => 'Convenience Fee',
                'convenience_fee' => null,
                'convenience_type_id' => null
            ],
            [
                'id' => 6,
                'name' => 'Paymaya Wallet',
                'is_enabled' => setting('IsPaymayaWalletEnabled', true),
                'is_globally_enabled' => setting('IsPaymayaWalletEnabled', true),
                'sort_number' => 2,
                'convenience_label' => 'Convenience Fee',
                'convenience_fee' => null,
                'convenience_type_id' => null
            ],
            [
                'id' => 5,
                'name' => 'Bank Transfer',
                'is_enabled' => setting('IsBankTransferEnabled', true),
                'is_globally_enabled' => setting('IsBankTransferEnabled', true),
                'sort_number' => 3,
                'payment_methods' => Bank::where('payment_channel', '_')
                    ->get()
                    ->map(function(Bank $bank) {
                        return [
                            'name' => $bank->name,
                            'code' => $bank->code,
                            'is_enabled' => $bank->is_enabled,
                            'is_globally_enabled' => $bank->is_enabled,
                            'image_path' => $bank->image_path,
                            'convenience_label' => 'Convenience Fee',
                            'convenience_fee' => null,
                            'convenience_type_id' => null
                        ];
                    }),
                'convenience_label' => 'Convenience Fee',
                'convenience_fee' => null,
                'convenience_type_id' => null
            ],
            [
                'id' => 1,
                'name' => 'Gcash',
                'is_enabled' => false ?? setting('isGcashEnabled', true),
                'is_globally_enabled' => setting('isGcashEnabled', true),
                'sort_number' => 4,
                'convenience_label' => 'Convenience Fee',
                'convenience_fee' => null,
                'convenience_type_id' => null
            ],
            [
                'id' => 2,
                'name' => 'GrabPay',
                'is_enabled' => false ?? setting('isGrabPayEnabled', true),
                'is_globally_enabled' => setting('isGrabPayEnabled', true),
                'sort_number' => 5,
                'convenience_label' => 'Convenience Fee',
                'convenience_fee' => null,
                'convenience_type_id' => null
            ]
        ];

        collect($paymentTypes)
            ->each(function ($paymentType) use($merchant) {
                $merchant->paymentTypes()->attach(
                    $paymentType['id'],
                    Arr::only($paymentType, [
                        'is_enabled',
                        'is_globally_enabled',
                        'sort_number',
                        'payment_methods',
                        'convenience_label',
                        'convenience_fee',
                        'convenience_type_id'
                    ])
                );
            });
    }

    /**
     * Create default recurrences for the merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function createRecurrences($merchant)
    {
        $merchant->recurrences()->createMany([
            [
                'name' => 'Single Order',
                'code' => 'single',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a one time delivery'
                    : 'Receive your order once',
                'sort_number' => 1,
            ],

            [
                'name' => 'Weekly',
                'code' => 'weekly',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery once per week'
                    : 'Receive your order once per week',
                'sort_number' => 2,
            ],

            [
                'name' => 'Every Other Week',
                'code' => 'semimonthly',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery once every two weeks'
                    : 'Receive your order once every two weeks',
                'sort_number' => 3,
            ],

            [
                'name' => 'Monthly',
                'code' => 'monthly',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery once per month'
                    : 'Receive your order once per month',
                'sort_number' => 4,
                'is_enabled' => true,
            ],

            [
                'name' => 'Every 2 Months',
                'code' => 'bimonthly',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery every two months'
                    : 'Receive your order every two months',
                'sort_number' => 5,
            ],

            [
                'name' => 'Quarterly',
                'code' => 'quarterly',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery every three months'
                    : 'Receive your order every three months',
                'sort_number' => 6,
                'is_enabled' => true,
            ],


            [
                'name' => 'Semi Annual',
                'code' => 'semiannual',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery every six months'
                    : 'Receive your order every six months',
                'sort_number' => 7,
            ],

            [
                'name' => 'Annually',
                'code' => 'annually',
                'description' => $merchant->has_shippable_products
                    ? 'Receive a new delivery every year'
                    : 'Receive your order every year',
                'sort_number' => 8,
                'is_enabled' => true,
            ]
        ]);
    }
}
