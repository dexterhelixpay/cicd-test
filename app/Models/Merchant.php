<?php

namespace App\Models;

use App\Casts\Html;
use App\Casts\JsonAssets;
use App\Facades\Viber;
use App\Libraries\Image;
use App\Libraries\Xendit\Account;
use App\Libraries\Viber\Message as ViberMessage;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Merchant extends RecordableModel
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Constant representing a X / Y sold stock display
     *
     * @var integer
     */
    const STOCK_XY_SOLD_DISPLAY = 1;


    /**
     * Constant representing a X sold already! stock display
     *
     * @var integer
     */
    const STOCK_X_SOLD_DISPLAY = 2;

    /**
     * Constant representing a none stock display
     *
     * @var integer
     */
    const STOCK_NONE_DISPLAY = 3;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pricing_type_id',

        'username',
        'email',
        'country_id',
        'mobile_number',
        'formatted_mobile_number',
        'password',

        'name',
        'subdomain',

        'shopify_domain',
        'shopify_store_link',
        'shopify_info',
        'shopify_metafield_definition_id',

        'tagline',
        'description_title',

        'logo_image_path',
        'background_color',
        'header_background_color',
        'button_background_color',
        'highlight_color',
        'on_background_color',
        'footer_background_color',
        'footer_text_color',
        'card_text_color',
        'button_background_color',
        'group_background_color',
        'group_highlight_color',
        'group_unhighlight_color',
        'on_hover_button_background_color',
        'login_text_color',
        'members_page_text_color',

        'website_url',
        'instagram_handle',

        'card_discount',

        'has_api_access',
        'has_digital_products',
        'has_shippable_products',
        'is_enabled',

        'are_orders_skippable',
        'are_orders_cancellable',
        'are_multiple_products_selectable',

        'faqs',
        'faqs_title',
        'is_faqs_enabled',

        'delivery_faqs',
        'delivery_faqs_title',
        'is_delivery_faqs_enabled',

        'marketing_card_image_url',
        'marketing_card_expires_at',

        'analytics_url',
        'support_contact',

        'is_credentials_visible',

        'confirmed_headline_text',
        'confirmed_subheader_text',

        'console_created_email_headline_text',
        'console_created_email_subheader_text',
        'console_created_email_subject',

        'late_payment_headline_text',
        'late_payment_subheader_text',
        'late_payment_subject_text',

        'incoming_payment_subject_text',
        'incoming_payment_subheader_text',
        'incoming_payment_headline_text',

        'due_payment_subject_text',
        'due_payment_subheader_text',
        'due_payment_headline_text',

        'shipped_email_headline_text',
        'shipped_email_subheader_text',

        'free_delivery_text',
        'free_delivery_threshold',

        'recurrence_title',

        'is_new_subscriber_email_enabled',

        'single_recurrence_title',
        'single_recurrence_subtitle',
        'single_recurrence_button_text',

        'has_reached_max_amount',

        'max_payment_limit',
        'hourly_total_amount_paid',

        'add_product_text',
        'add_product_text_color',

        'is_subscriptions_editable',
        'is_estimated_delivery_date_enabled',
        'are_memberships_enabled',
        'is_enabled_fulfillment_status',

        'customer_promo_image_url',

        'pay_button_text',
        'recurring_button_text',

        'shopify_api_key',
        'shopify_secret_key',

        'is_custom_fields_enabled',

        'viber_uri',
        'viber_key',

        'shipping_days_after_payment',
        'fulfillment_days_after_payment',

        'convenience_label',
        'convenience_fee',
        'convenience_type_id',

        'auto_cancellation_days',

        'subscription_term_singular',
        'subscription_term_plural',

        'facebook_pixel_code',
        'is_facebook_pixel_enabled',

        'payment_alert_title',
        'payment_alert_subtitle',
        'is_payment_alert_enabled',
        'is_promo_code_enabled',

        'tooltip_title',
        'tooltip_subtitle',

        'is_shopify_order_prices_editable',

        'invoice_corporate_info',
        'has_corporate_info_on_invoice',

        'is_vat_enabled',

        'membership_banner_path',
        'membership_header_text',
        'membership_subheader_text',
        'membership_banner_border_config',
        'order_banner_border_config',
        'is_special_rounding',

        'font_settings',
        'margin_settings',
        'drop_shadow_settings',

        'align_banner_to_product_cards',

        'viber_info',

        'is_telegram_enabled',
        'telegram_header_text',
        'telegram_subheader_text',
        'telegram_invite_button_text',

        'is_card_auto_reminder_enabled',
        'is_wallet_auto_reminder_enabled',
        'is_outstanding_balance_enabled',
        'previous_balance_label',

        'product_details_button_color',
        'product_details_button_text',

        'product_select_button_color',
        'product_select_button_text',

        'products_video_banner',
        'members_video_banner',

        'is_logo_visible',
        'is_home_banner_visible',
        'is_membership_banner_visible',
        'is_members_video_banner_visible',
        'is_products_video_banner_visible',
        'is_customer_promo_image_visible',

        'is_product_details_enabled',
        'uses_paymaya_sdk',
        'discord_guild_id',

        'storefront_headline_text',
        'storefront_headline_css',
        'storefront_background_image_path',
        'storefront_background_image_css',

        'email_font_settings',
        'membership_blast_font_settings',

        'colors',
        'discord_days_unpaid_limit',

        'is_discord_settings_enabled',
        'is_discord_email_invite_enabled',

        'is_members_page_button_enabled',
        'members_login_banner_path',
        'members_login_text',

        'home_banner_video_link',
        'is_address_enabled',
        'buttons',

        'product_stock_counter_type',

        'storefront_success_text',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',

        'paymaya_vault_public_key',
        'paymaya_vault_secret_key',
        'paymaya_pwp_public_key',
        'paymaya_pwp_secret_key',

        'paymaya_vault_console_public_key',
        'paymaya_vault_console_secret_key',
        'paymaya_pwp_console_public_key',
        'paymaya_pwp_console_secret_key',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_address_enabled' => 'boolean',
        'align_banner_to_product_cards' => 'boolean',
        'shopify_info' => 'array',
        'storefront_success_text' => 'array',
        'font_settings' => 'array',
        'margin_settings' => 'array',
        'drop_shadow_settings' => 'array',
        'email_font_settings' => 'array',
        'membership_blast_font_settings' => 'array',
        'viber_info' => 'array',
        'has_api_access' => 'boolean',
        'has_digital_products' => 'boolean',
        'has_shippable_products' => 'boolean',
        'description_items' => 'array',
        'are_orders_skippable' => 'boolean',
        'are_orders_cancellable' => 'boolean',
        'is_enabled' => 'boolean',
        'is_faqs_enabled' => 'boolean',
        'is_credentials_visible' => 'boolean',
        'are_multiple_products_selectable' => 'boolean',
        'is_custom_domain_used' => 'boolean',
        'is_new_subscriber_email_enabled' => 'boolean',
        'has_reached_max_amount' => 'boolean',
        'is_subscriptions_editable' => 'boolean',
        'is_estimated_delivery_date_enabled' => 'boolean',
        'are_memberships_enabled' => 'boolean',
        'is_enabled_fulfillment_status' => 'boolean',
        'is_shopify_order_prices_editable' => 'boolean',
        'has_corporate_info_on_invoice' => 'boolean',
        'invoice_corporate_info' => Html::class,
        'is_special_rounding' => 'boolean',
        'is_telegram_enabled' => 'boolean',
        'is_card_auto_reminder_enabled' => 'boolean',
        'is_wallet_auto_reminder_enabled' => 'boolean',
        'is_outstanding_balance_enabled' => 'boolean',
        'is_logo_visible' => 'boolean',
        'is_home_banner_visible' => 'boolean',
        'is_membership_banner_visible' => 'boolean',
        'is_members_video_banner_visible' => 'boolean',
        'is_products_video_banner_visible' => 'boolean',
        'is_customer_promo_image_visible' => 'boolean',
        'is_product_details_enabled' => 'boolean',
        'is_discord_settings_enabled' => 'boolean',
        'is_discord_email_invite_enabled' => 'boolean',
        'is_members_page_button_enabled' => 'boolean',
        'uses_paymaya_sdk' => 'boolean',
        'membership_banner_border_config' => 'array',
        'order_banner_border_config' => 'array',
        'storefront_headline_css' => 'array',
        'storefront_background_image_css' => 'array',
        'faqs' => Html::class,
        'delivery_faqs' => Html::class,
        'members_login_text' => Html::class,
        'colors' => 'collection',
        'buttons' => 'array',
        'support_contact' => 'array',
        'copies' => 'collection',
        'images' => JsonAssets::class,
    ];

    /**
     * The attributes that are considered as assets.
     *
     * @var array
     */
    protected $assets = [
        'logo_image_path',
        'logo_svg_path',
        'favicon_path',
        'home_banner_path',
        'membership_banner_path',
        'members_login_banner_path',
        'marketing_card_image_path',
        'customer_promo_image_path',
        'storefront_background_image_path'
    ];

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->when(!is_numeric($value), function ($query) use ($value) {
                $query->orWhere('subdomain', $value);
            })
            ->first();
    }

    /**
     * Route notifications for the Viber channel.
     *
     * @param  \Illuminate\Notifications\Notification|null
     * @return string
     */
    public function routeNotificationForViber($notification = null)
    {
        if (!$this->viber_info) return null;

        return $this->viber_info['id'];
    }


    /**
     * Get the path to the merchant's home banner.
     *
     * @return string
     */
    public function getHomeBannerPathAttribute($value)
    {
        if (Str::contains($value, 'https')) return $value;

        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the merchant's members login banner image.
     *
     * @return string
     */
    public function getMembersLoginBannerPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }


    /**
     * Get the path to the merchant's background image.
     *
     * @return string
     */
    public function getStorefrontBackgroundImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

      /**
     * Get the country.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }


    /**
     * Get the path to the merchant's membership banner.
     *
     * @return string
     */
    public function getMembershipBannerPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the merchant's favicon.
     *
     * @return string
     */
    public function getFaviconPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the merchant's logo.
     *
     * @return string
     */
    public function getLogoImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the merchant's SVG logo.
     *
     * @return string
     */
    public function getLogoSvgPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the merchant's SVG logo.
     *
     * @return string
     */
    public function getMarketingCardImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the merchant's SVG logo.
     *
     * @return string
     */
    public function getCustomerPromoImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the API keys.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Get the order notification.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderNotifications(): HasMany
    {
        return $this->hasMany(OrderNotification::class);
    }

    /**
     * Get the posts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

     /**
     * Get the vouchers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Get the vouchers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function followUpEmails(): HasMany
    {
        return $this->hasMany(MerchantFollowUpEmail::class);
    }

     /**
     * Get the payment types.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function paymentTypes(): BelongsToMany
    {
        return $this
            ->belongsToMany(PaymentType::class, 'merchant_payment_types')
            ->withPivot([
                'is_enabled',
                'is_globally_enabled',
                'sort_number',
                'payment_methods',
                'convenience_label',
                'convenience_fee',
                'convenience_type_id'
            ])
            ->orderBy('sort_number');
    }

     /**
     * Get the announcements.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function announcements(): BelongsToMany
    {
        return $this
            ->belongsToMany(Announcement::class)
            ->withPivot(['expires_at']);
    }

     /**
     * Get the table columns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tableColumns(): BelongsToMany
    {
        return $this
            ->belongsToMany(TableColumn::class)
            ->withPivot([
                'table',
                'sort_number'
            ])
            ->orderBy('sort_number');
    }

    /**
     * Get the checkouts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function checkouts(): HasMany
    {
        return $this->hasMany(Checkout::class);
    }

    /**
     * Get the product groups of merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productGroups(): HasMany
    {
        return $this->hasMany(MerchantProductGroup::class);
    }

    /**
     * Get the email blasts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emailBlasts(): HasMany
    {
        return $this->hasMany(MerchantEmailBlast::class);
    }

    /**
     * Get the welcome emails.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function welcomeEmails(): HasMany
    {
        return $this->hasMany(WelcomeEmail::class);
    }

    /**
     * Get the schedule emails.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function scheduleEmails(): HasMany
    {
        return $this->hasMany(ScheduleEmail::class);
    }

    /**
     * Get the draft email blasts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emailBlastDrafts(): HasMany
    {
        return $this->hasMany(MerchantEmailBlast::class)
            ->where('is_draft', true);
    }

    /**
     * Get the finances.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function finances(): HasMany
    {
        return $this->hasMany(MerchantFinance::class);
    }

    /**
     * Get the customers.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the descriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function descriptionItems(): HasMany
    {
        return $this->hasMany(MerchantDescriptionItem::class);
    }

    /**
     * Get the import batches
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    /**
     * Get the custom fields.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * Get the subscription custom fields.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptionCustomFields(): HasMany
    {
        return $this->hasMany(SubscriptionCustomField::class);
    }

    /**
     * Get the custom components.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customComponents(): HasMany
    {
        return $this->hasMany(CustomComponent::class)->orderBy('sort_number', 'asc');
    }


    /**
     * Get the subscription imports.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptionImports(): HasMany
    {
        return $this->hasMany(SubscriptionImport::class);
    }

    /**
     * Get the social links.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class)->where('is_footer', false)->orderBy('sort_number');
    }

    /**
     * Get the social links.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function footerLinks(): HasMany
    {
        return $this->hasMany(SocialLink::class)->where('is_footer', true)->orderBy('sort_number');
    }


    /**
     * Get the subscription orders.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Subscription::class);
    }

    /**
     * Get the owner.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function owner(): HasOne
    {
        return $this->hasOne(MerchantUser::class)
            ->ofMany(['id' => 'max'], function ($query) {
                $query->role('Owner');
            });
    }

    /**
     * Get the associated PayMaya merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymayaMerchant(): BelongsTo
    {
        return $this->belongsTo(PaymayaMerchant::class);
    }

    /**
     * Get the associated PayMaya PwP MID.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymayaPwpMid(): BelongsTo
    {
        return $this->belongsTo(PaymayaMid::class, 'paymaya_pwp_mid_id');
    }

    /**
     * Get the associated PayMaya Vault MID.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymayaVaultMid(): BelongsTo
    {
        return $this->belongsTo(PaymayaMid::class, 'paymaya_vault_mid_id');
    }

      /**
     * Get the associated PayMaya PwP MID.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymayaPwpConsoleMid(): BelongsTo
    {
        return $this->belongsTo(PaymayaMid::class, 'paymaya_pwp_mid_console_id');
    }

    /**
     * Get the associated PayMaya Vault MID.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymayaVaultConsoleMid(): BelongsTo
    {
        return $this->belongsTo(PaymayaMid::class, 'paymaya_vault_mid_console_id');
    }


    /**
     * Get the products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the recurrences.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recurrences(): HasMany
    {
        return $this->hasMany(MerchantRecurrence::class)->orderBy('sort_number');
    }

    /**
     * Get the shipping methods.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shippingMethods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    /**
     * Get the subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(MerchantUser::class);
    }

    /**
     * Get the webhooks.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    /**
     * Get all the requests to the merchant's webhooks.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function webhookRequests(): HasMany
    {
        return $this->hasMany(WebhookRequest::class);
    }

    /**
     * Get the webhook key.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function webhookKey(): HasOne
    {
        return $this->hasOne(MerchantWebhookKey::class)->ofMany();
    }

    /**
     * Get the webhook account.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function xenditAccount(): HasOne
    {
        return $this->hasOne(XenditAccount::class)->ofMany();
    }

    /**
     * Check if the merchant has PayMaya PwP keys.
     *
     * @return bool
     */
    public function hasPwpKeys()
    {
        $hasPwpKeys = !is_null($this->paymaya_pwp_public_key)
            && !is_null($this->paymaya_pwp_secret_key);

        $hasConsolePwpKeys = !is_null($this->paymaya_pwp_console_public_key)
            && !is_null($this->paymaya_pwp_console_secret_key);

        return $hasPwpKeys || $hasConsolePwpKeys;
    }

    /**
     * Check if the merchant has a Shopify account.
     *
     * @return bool
     */
    public function hasShopifyAccount()
    {
        return !is_null($this->shopify_domain)
            && !is_null($this->shopify_info);
    }

    /**
     * Check if the merchant has PayMaya Vault keys.
     *
     * @return bool
     */
    public function hasVaultKeys()
    {
        $hasVaultKeys = !is_null($this->paymaya_vault_public_key)
            && !is_null($this->paymaya_vault_secret_key);

        $hasConsoleVaultKeys = !is_null($this->paymaya_vault_console_public_key)
            && !is_null($this->paymaya_vault_console_secret_key);

        return $hasVaultKeys || $hasConsoleVaultKeys;
    }

    /**
     * Send viber notification to merchant
     *
     * @return bool
     */
    public function sendViberNotification($message)
    {
        $merchant = $this;

        if (!$merchant->viber_info) return;

        Viber::withToken(
            config('services.viber.merchant_auth_token'),
            function () use($merchant, $message) {
                return ViberMessage::send(
                    $merchant->viber_info['id'],
                    $message
                );
            }
        );
    }

    /**
     * Check if the merchant's Xendit account is live.
     *
     * @return bool
     */
    public function isXenditLive()
    {
        $statuses = [Account::STATUS_LIVE];

        if (!app()->isProduction()) {
            $statuses = array_merge($statuses, [Account::STATUS_REGISTERED]);
        }

        $account = $this->xenditAccount()->first();

        return $account
            && $account->xendit_account_id
            && in_array($account->status, $statuses);
    }

    /**
     * Check if the merchant is verified.
     *
     * @return bool
     */
    public function isVerified()
    {
        return !is_null($this->verified_at);
    }

    /**
     * Replace the subscription terms of the given text.
     *
     * @param  string|string[]  $text
     * @return string|string[]
     */
    public function replaceSubscriptionTerms($text)
    {
        $replaceFn = function ($text) {
            $text = str_replace(
                ':subscriptions', $this->subscription_term_plural ?? 'subscriptions', $text
            );

            $text = str_replace(
                ':subscription', $this->subscription_term_singular ?? 'subscription', $text
            );

            return $text;
        };

        return is_array($text) ? array_map($replaceFn, $text) : $replaceFn($text);
    }

    /**
     * Sync the merchant's descriptions.
     *
     * @param  array  $items
     * @return self
     */
    public function syncDescriptionItems($items)
    {
        ksort($items);

        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->descriptionItems()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)->each(function ($item, $index) {
            $descItem = $this->descriptionItems()->findOrNew(data_get($item, 'id'));
            $descItem->fill(Arr::only($item['attributes'] ?? [], ['emoji', 'description']) + [
                'sort_number' => (int) $index + 1,
            ]);

            if ($icon = data_get($item, 'attributes.icon')) {
                if (!is_string($icon)) {
                    $descItem->uploadIcon($icon);
                    $descItem->emoji = null;
                }
            } elseif ($descItem->emoji) {
                $descItem->icon_path = null;
            }

            $descItem->save();
        });

        return $this;
    }

    /**
     * Sync the merchant's descriptions.
     *
     * @param  array  $items
     * @return self
     */
    public function syncSocialLinks($items)
    {
        ksort($items);

        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->socialLinks()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)->each(function ($item, $index) {
            $socialLink = $this->socialLinks()->findOrNew(data_get($item, 'id'));
            $socialLink->fill(data_get($item, 'attributes') + [
                'sort_number' => (int) $index + 1,
            ]);
            $socialLink->save();
        });

        return $this;
    }

    /**
     * Sync the merchant's custom fields.
     *
     * @param  array  $items
     * @return self
     */
    public function syncCustomFields($items)
    {
        if (!$items) {
            $this->customFields()->delete();
            return $this;
        }

        ksort($items);

        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->customFields()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)
            ->whereNotNull('label')
            ->each(function ($item, $index) {
                if ( $item['label'] !== '' && $item['label'] !== 'undefined') {
                    $item['code'] = STr::camel($item['label']);
                    $item['label'] = ucwords(strtolower($item['label']));
                    $this->customFields()->updateOrCreate(Arr::only($item,'id'),Arr::except($item, 'id'));
                }
            });

        return $this;
    }

    /**
     * Sync the merchant's subscription custom fields.
     *
     * @param  array  $items
     * @return self
     */
    public function syncSubsCustomFields($items)
    {
        if (!$items) {
            $this->subscriptionCustomFields()->delete();
            return $this;
        }

        ksort($items);

        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->subscriptionCustomFields()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)
            ->whereNotNull('attributes.label')
            ->each(function ($item) {
                $label = data_get($item, 'attributes.label');

                if ( $label !== '' && $label !== 'undefined' && $label !== null) {
                    data_set($item, 'attributes.label', ucwords(strtolower($label)));
                    data_set($item, 'attributes.code', STr::camel($label));
                    $this->subscriptionCustomFields()
                        ->updateOrCreate(Arr::only($item,'id'), data_get($item, 'attributes'));
                }
            });

        return $this;
    }

    /**
     * Upload the given favicon.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return self
     */
    public function uploadFavicon($image)
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.png";

        $image = new Image($image);
        $image->resize(16, 16)->encode('png', 100);
        $image->put($path);

        return $this->setAttribute('favicon_path', $path);
    }

    /**
     * Upload the given banner.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return self
     */
    public function uploadMembershipBanner($image)
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $type = $image->getClientOriginalExtension();

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                $path = "{$directory}/{$fileRoot}.png";

                $image = new Image($image);
                $image->encode('png', 100);
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$type}";
                Storage::put($path, $image->getContent());
                break;
        }

        return $this->setAttribute('membership_banner_path', $path);
    }

    /**
     * Upload the given store front.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return self
     */
    public function uploadHomeBanner($image)
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $type = $image->getClientOriginalExtension();

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                $path = "{$directory}/{$fileRoot}.png";

                $image = new Image($image);
                $image->encode('png', 100);
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$type}";
                Storage::put($path, $image->getContent());
                break;
        }

        $this->setAttribute('home_banner_video_link', null);
        return $this->setAttribute('home_banner_path', $path);
    }

    /**
     * Upload the given image.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return self
     */
    public function uploadMarketingCardImage($image)
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.png";

        $image = (new Image($image))->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
        });
        $image->encode('png');
        $image->put($path);

        return $this->setAttribute('marketing_card_image_path', $path);
    }

    /**
     * Upload the given image.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return self
     */
    public function uploadCustomerPromoImage($image)
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());

        $type = $image->getClientOriginalExtension();

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'JPG':
            case 'jpeg':
                $path = "{$directory}/{$fileRoot}.png";

                $image = (new Image($image))->resize(400, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $image->encode('png');
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$type}";
                Storage::put($path, $image->getContent());
                break;
        }

        return $this->setAttribute('customer_promo_image_path', $path);
    }

    /**
     * Upload the given image.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return self
     */
    public function uploadBackgroundImage($image)
    {
        $directory = "images/merchants/{$this->id}";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());

        $type = $image->getClientOriginalExtension();

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                $path = "{$directory}/{$fileRoot}.png";

                $image = (new Image($image))->resize(2000, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
                $image->encode('png');
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$type}";
                Storage::put($path, $image->getContent());
                break;
        }


        return $this->setAttribute('storefront_background_image_path', $path);
    }


    /**
     * Upload the given logo.
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @param  string  $type
     * @return self
     */
    public function uploadMembersLoginImage($image, $type = 'png')
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}";

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                $attribute = 'members_login_banner_path';
                $image = $image instanceof UploadedFile
                    ? $image->getRealPath()
                    : $image;
                $path .= ".png";
                $image = new Image($image);
                $image->encode($type);
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$image->getClientOriginalExtension()}";
                $attribute = 'members_login_banner_path';

                Storage::put($path, $image->getContent());
                break;
            case 'bmp':
            case 'svg':
                $attribute = 'members_login_banner_path';
                $image = $image instanceof UploadedFile
                    ? $image->getContent()
                    : $image;
                $path .= ".{$type}";

                Storage::put($path, $image);

                break;
        }

        return $this->setAttribute($attribute, $path);
    }

    /**
     * Upload the given logo.
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @param  string  $type
     * @return self
     */
    public function uploadLogo($image, $type = 'png')
    {
        $directory = 'images/merchants';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}";

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                $attribute = 'logo_image_path';
                $image = $image instanceof UploadedFile
                    ? $image->getRealPath()
                    : $image;

                $path .= '.png';

                $image = new Image($image);
                $image->encode('png', 100);
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$image->getClientOriginalExtension()}";
                $attribute = 'logo_image_path';

                Storage::put($path, $image->getContent());
                break;
            case 'bmp':
            case 'svg':
                $attribute = $type == 'bmp'
                    ? 'logo_image_path'
                    : 'logo_svg_path';
                $path .= ".{$type}";

                $image = $image instanceof UploadedFile
                    ? $image->getContent()
                    : $image;

                Storage::put($path, $image);

                break;
        }

        return $this->setAttribute($attribute, $path);
    }

    /**
     * Merge attributes with defaults.
     *
     * @return $this
     */
    public function mergeDefaults()
    {
        $this->copies = collect($this->copies ?: [])
            ->union([
                'soldout_sticker_text' => 'Item Sold Out',
            ]);

        return $this;
    }

    /**
     * Upload the given image.
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @param  string  $key
     * @return $this
     */
    public function uploadImage($image, $key)
    {
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $extension = $image instanceof UploadedFile
            ? $image->getClientOriginalExtension()
            : 'png';

        switch ($key) {
            case 'soldout_banner':
                $directory = "images/banners/soldout";
                $callback = fn ($img) => (new Image($img))->resizeMax(600, 400);

                break;

            default:
                throw new Exception("Unsupported image key: {$key}");
        }

        $path = "{$directory}/{$fileRoot}";

        if ($extension === 'gif') {
            $path .= ".{$extension}";
            Storage::put($path, $image->getContent());
        } else {
            $path .= '.png';
            $img = isset($callback) ? $callback($image) : new Image($image);

            tap($img, fn ($img) => $img->encode('png'))->put($path);
        }

        $this->images = collect($this->images ?: [])->merge([$key => $path]);

        return $this;
    }
}
