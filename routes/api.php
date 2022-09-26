<?php

use App\Http\Controllers\Api\MainController;
use App\Http\Controllers\Api\v1\DeepLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MainController::class, 'index'])->name('index');
Route::get('/{url_key}', [DeepLinkController::class, 'redirect']);

Route::name('v1.')
    ->prefix('v1')
    ->namespace('v1')
    ->group(function () {
        Route::apiResource('api_keys', 'ApiKeyController');
        Route::apiResource('api_requests', 'ApiRequestController')->only('index');

        Route::post('commands', 'CommandController@run')->name('commands.run');

        Route::apiResource('checkouts', 'CheckoutController', [
            'only' => ['store'],
        ]);

        Route::apiResource('announcements', 'AnnouncementController');

        Route::apiResource('home_page_cards', 'HomePageCardController');
        Route::post('home_page_cards/sort', 'HomePageCardController@sort')->name('home_page_cards.sort');
        Route::post('home_page_cards/{card}', 'HomePageCardController@update')->name('home_page_cards.update');

        Route::apiResource('custom_fields', 'CustomFieldController', [
            'only' => ['index'],
        ]);

        Route::apiResource('subscription_imports', 'SubscriptionImportController', [
            'only' => ['index'],
        ]);

        Route::apiResource('table_columns', 'TableColumnController',[
            'only' => ['index', 'show'],
        ]);

        Route::apiResource('users', 'UserController');
        Route::post('users/{user}/change_password', 'UserController@changePassword');

        Route::apiResource('roles', 'RoleController',[
            'only' => ['index','show'],
        ]);

        Route::apiResource('permissions', 'PermissionController',[
            'only' => ['index','show'],
        ]);

        Route::get('customers/template', 'CustomerController@downloadTemplate');
        Route::apiResource('customers', 'CustomerController');

        Route::post('customers/{customer}/send_payment_reminder', 'CustomerController@sendPaymentReminder');

        Route::apiResource('merchants', 'MerchantController');
        Route::post('merchants/{merchant}', 'MerchantController@update')->name('merchants.post_update');
        Route::post('merchants/{merchant}/compute_order_prices', 'MerchantController@getComputedOrderPrices');
        Route::post('merchants/{merchant}/send_test_email', 'MerchantController@sendTestEmail');
        Route::get('merchants/{merchant}/home_page_cards', 'HomePageCardController@getMerchantCards');

        Route::apiResource('orders', 'OrderController');
        Route::put('orders', 'OrderController@bulkUpdate')->name('orders.bulk_update');
        Route::get('orders/{order}/download_invoice', 'OrderController@downloadInvoice')->name('orders.download.invoice');

        Route::apiResource('payment_error_responses', 'PaymentErrorResponseController', [
            'only' => ['index', 'store', 'update', 'destroy'],
        ]);

        Route::apiResource('products', 'ProductController',[
            'only' => ['index', 'store', 'show', 'update'],
        ]);

        Route::post('products/generate_slug', 'ProductController@generateSlug')->name('product.generate_slug');
        Route::post('products/generate_deep_link', 'ProductController@generateDeepLink')->name('product.generate_deep_link');
        Route::post('products/{product_id}/reset_sales', 'ProductController@resetSales');

        Route::apiResource('product_groups', 'ProductGroupController', [
            'only' => ['index'],
        ]);

        Route::apiResource('subscription_custom_fields', 'SubscriptionCustomFieldController', [
            'only' => ['index'],
        ]);

        Route::apiResource('variants', 'VariantController',[
            'only' => ['index'],
        ]);

        Route::post('products/{product}', 'ProductController@update')->name('products.post_update');

        Route::name('products.')
            ->prefix('products/{product}')
            ->namespace('Product')
            ->group(function () {
                Route::apiResource('description_items', 'DescriptionItemController', [
                    'only' => ['index'],
                ]);

                Route::apiResource('variants', 'VariantController', [
                    'only' => ['index'],
                ]);

                Route::apiResource('shipping_fees', 'ShippingFeeController');

                Route::apiResource('teaser_cards', 'TeaserCardController');
                Route::put('teaser_cards', 'TeaserCardController@bulkUpdate')
                    ->name('teaser_cards.bulk_update');
                Route::post('teaser_cards/{teaser_card}', 'TeaserCardController@update')
                    ->name('teaser_cards.post_update');

                Route::post('description_items', 'DescriptionItemController@bulkUpdate')
                    ->name('items.bulk_update');
            });

        Route::apiResource('provinces', 'ProvinceController',[
            'only' => ['index', 'show'],
        ]);

        Route::apiResource('countries', 'CountryController',[
            'only' => ['index', 'show'],
        ]);

        Route::apiResource('social_links', 'SocialLinkIconController',[
            'only' => ['index', 'show'],
        ]);

        Route::apiResource('batches', 'BatchController',[
            'only' => ['show'],
        ]);

        Route::apiResource('payment_types', 'PaymentTypeController', [
            'only' => ['index'],
        ]);

        Route::apiResource('shipping_methods', 'ShippingMethodController', [
            'only' => ['index'],
        ]);

        Route::post('subscriptions/import', 'SubscriptionController@import')
            ->name('subscriptions.import');

        Route::post('subscriptions/parse', 'SubscriptionController@parse')
            ->name('subscriptions.parse');

        Route::get('subscriptions/template', 'SubscriptionController@downloadTemplate')
            ->name('subscriptions.template');

        Route::post('shipping_fees/import', 'Product\ShippingFeeController@import')
            ->name('shipping_fees.import');
        Route::get('shipping_fees/template', 'Product\ShippingFeeController@downloadTemplate')
            ->name('shipping_fees.template');

        Route::apiResource('subscriptions', 'SubscriptionController', [
            'only' => ['index', 'store', 'update'],
        ]);


        Route::put('subscriptions/{subscription}/cancel', 'SubscriptionController@cancel')
            ->name('subscriptions.cancel');

        Route::post('import_finances', 'MerchantController@importFinances')
            ->name('merchants.import_finances');

        Route::post('generate_merchant_slug', 'MerchantController@generateSlug')
            ->name('merchants.slug');

        Route::apiResource('merchant_users', 'MerchantUserController', [
            'only' => ['index'],
        ]);

        Route::name('product_groups.')
            ->prefix('product_groups/{productGroup}')
            ->namespace('ProductGroup')
            ->group(function () {
                Route::apiResource('products', 'ProductController', [
                    'only' => ['index'],
                ]);
            });

        Route::post('vouchers/validate', 'Merchant\VoucherController@validateVoucher')->name('vouchers.validate');
        Route::post('vouchers/send_otp', 'Merchant\VoucherController@sendOtp')->name('vouchers.send.otp');
        Route::post('vouchers/validate_otp', 'Merchant\VoucherController@validateOtp')->name('vouchers.validate.otp');
        Route::get('vouchers/template', 'Merchant\VoucherController@downloadTemplate');

        Route::post('memberships/validate', 'Merchant\SubscriptionController@validateMembership')->name('subscriptions.validate');

        Route::get('customers/{customer}/get_cards', 'CardController@index');
        Route::get('customers/{customer}/get_wallets', 'WalletController@index');

        Route::apiResource('customers/{customer}/subscriptions', 'Customer\SubscriptionController', [
            'only' => ['index'],
        ]);

        Route::name('customers.')
            ->prefix('customers/{customer}')
            ->namespace('Customer')
            ->group(function () {
                Route::apiResource('cards', 'CardController', [
                    'only' => ['index', 'destroy'],
                ]);

                Route::apiResource('wallets', 'WalletController', [
                    'only' => ['index', 'destroy'],
                ]);

                Route::apiResource('orders', 'OrderController', [
                    'only' => ['index'],
                ]);
            });

        Route::name('orders.')
            ->prefix('orders/{order}')
            ->namespace('Order')
            ->group(function () {
                Route::apiResource('attachments', 'AttachmentController', [
                    'only' => ['index', 'store', 'destroy'],
                ]);

                Route::apiResource('products', 'ProductController', [
                    'only' => ['index', 'update'],
                ]);

                Route::put('products', 'ProductController@bulkUpdate')->name('bulk_update');
            });

        Route::name('subscriptions.')
            ->prefix('subscriptions/{subscription}')
            ->namespace('Subscription')
            ->group(function () {
                Route::apiResource('products', 'ProductController', [
                    'only' => ['index', 'update'],
                ]);

                Route::put('products', 'ProductController@bulkUpdate')->name('bulk_update');

                Route::apiResource('orders', 'OrderController');
            });

        Route::namespace('Merchant')
            ->group(function () {
                Route::apiResource('draft_merchants', 'DraftMerchantController',[
                    'only' => ['index', 'store'],
                ]);
            });

        Route::name('merchants.')
            ->prefix('merchants/{merchant}')
            ->namespace('Merchant')
            ->group(function () {
                Route::get('client', 'ClientController@show')->name('client.show');
                Route::post('client', 'ClientController@store')->name('client.store');

                Route::apiResource('footer_links', 'FooterLinkController');
                Route::post('footer_links/{footer_link}', 'FooterLinkController@update')
                    ->name('footer_links.post_update');
                Route::put('footer_links', 'FooterLinkController@bulkUpdate')->name('footer_links.bulk_update');

                Route::apiResource('custom_components', 'CustomComponentController');

                Route::apiResource('webhook_keys', 'WebhookKeyController', [
                    'only' => ['index', 'store'],
                ]);

                Route::apiResource('announcements', 'AnnouncementController', [
                    'only' => ['index', 'show']
                ]);

                Route::apiResource('subscription_imports', 'SubscriptionImportController', [
                    'only' => ['index'],
                ]);

                Route::apiResource('schedule_emails', 'ScheduleEmailController', [
                    'only' => ['index','store','show'],
                ]);

                Route::post('xendit_account', 'XenditAccountController@store')->name('xendit_account.store');
                Route::put('xendit_account', 'XenditAccountController@update')->name('xendit_account.update');

                Route::get('customers/export', 'CustomerController@export')->name('customers.export');
                Route::apiResource('customers', 'CustomerController');
                Route::get('email_blasts/preview', 'EmailBlastController@previewEmailBlast')->name('email_blasts.preview_email_blast');
                Route::apiResource('email_blasts', 'EmailBlastController');
                Route::post('email_blasts/{emailBlast}', 'EmailBlastController@update')
                    ->name('email_blasts.post_update');

                Route::apiResource('followup_emails', 'FollowUpController');
                Route::apiResource('paymaya_mids', 'MidController');

                Route::apiResource('products', 'ProductController');
                Route::post('products/{product}', 'ProductController@update')->name('products.post_update');
                Route::put('products', 'ProductController@bulkUpdate')->name('products.bulk_update');
                Route::delete('products', 'ProductController@deleteAll');

                Route::get('orders/export', 'OrderController@export')->name('orders.export');
                Route::get('orders/export_order_summary', 'OrderController@exportOrderSummary')->name('orders.export_order_summary');
                Route::get('orders/export_order_details', 'OrderController@exportOrderDetails')->name('orders.export_order_details');
                Route::post('orders/send_payment_reminder', 'OrderController@bulkSendPaymentReminder')->name('orders.send_payment_reminder');
                Route::post('orders/shipped_or_fulfilled', 'OrderController@bulkUpdateShippedOrFulfilled')->name('orders.shipped_or_fulfilled');
                Route::apiResource('orders', 'OrderController');
                Route::post('orders/{order}', 'OrderController@update')
                    ->name('orders.post_update');

                Route::apiResource('recurrences', 'RecurrenceController');

                Route::apiResource('vouchers', 'VoucherController');
                Route::put('vouchers', 'VoucherController@bulkUpdate')->name('vouchers.bulk_update');
                Route::post('vouchers/{voucher}', 'VoucherController@update')->name('vouchers.post_update');

                Route::apiResource('shipping_methods', 'ShippingMethodController');
                Route::apiResource('subscription_custom_fields', 'SubscriptionCustomFieldController');

                Route::get('subscriptions/export', 'SubscriptionController@export')->name('subscriptions.export');
                Route::apiResource('subscriptions', 'SubscriptionController');
                Route::post('subscriptions/{subscription}', 'SubscriptionController@update')
                    ->name('subscriptions.post_update');

                Route::post('subscriptions/{subscription}/apply_voucher', 'SubscriptionController@applyVoucher')
                    ->name('subscriptions.apply_voucher');
                Route::post('subscriptions/{subscription}/remove_voucher', 'SubscriptionController@removeVoucher')
                    ->name('subscriptions.remove_voucher');

                Route::apiResource('finances', 'FinanceController');

                Route::apiResource('users', 'UserController');
                Route::post('users/{user}/change_password', 'UserController@changePassword')
                    ->name('users.change_password');

                Route::apiResource('product_groups', 'ProductGroupController');
                Route::post('product_groups/{productGroup}', 'ProductGroupController@update')
                    ->name('product_groups.update');
                Route::put('product_groups', 'ProductGroupController@bulkUpdate')->name('product_groups.bulk_update');

                Route::apiResource('custom_fields', 'CustomFieldController', [
                    'only' => ['index', 'store', 'destroy']
                ]);

                Route::apiResource('welcome_emails', 'WelcomeEmailController');
                Route::post('welcome_emails/{welcome_email}', 'WelcomeEmailController@update')->name('welcome_emails.post_update');

                Route::apiResource('shopify_products', 'ShopifyProductController');
                Route::post('shopify_products/reload', 'ShopifyProductController@reload');
            });

        Route::apiResource('paymaya_mids', 'PaymayaMidController', [
            'only' => ['index'],
        ]);

        Route::name('payments.')
            ->prefix('payments')
            ->namespace('Payment')
            ->group(function () {
                Route::name('paymaya.')
                    ->prefix('paymaya')
                    ->group(function () {
                        Route::get('redirect', 'PayMayaController@redirect')->name('redirect');
                        Route::post('events', 'PayMayaController@captureEvent')->name('events');
                        Route::post('validate_card', 'PayMayaController@validateCard')->name('validate');
                    });

                Route::name('pesopay.')
                    ->prefix('pesopay')
                    ->group(function () {
                        Route::get('redirect', 'PesoPayController@redirect')->name('redirect');
                        Route::post('events', 'PesoPayController@captureEvent')->name('events');
                    });

                Route::name('brankas.')
                    ->prefix('brankas')
                    ->group(function () {
                        Route::get('redirect', 'BrankasController@redirect')->name('redirect');
                        Route::post('events', 'BrankasController@captureEvent')->name('events');
                    });

                Route::name('paymongo.')
                    ->prefix('paymongo')
                    ->group(function () {
                        Route::get('redirect', 'PayMongoController@redirect')->name('redirect');
                        Route::post('events', 'PayMongoController@captureEvent')->name('events');
                    });

                Route::name('xendit.')
                    ->prefix('xendit')
                    ->group(function () {
                        Route::get('redirect', 'XenditController@redirect')->name('redirect');
                        Route::post('events', 'XenditController@captureEvent')->name('events');
                    });
            });

        Route::apiResource('settings', 'SettingController');

        Route::name('shopify.')
            ->prefix('shopify')
            ->namespace('Shopify')
            ->group(function () {
                Route::post('products', 'ProductController@captureTopic')->name('capture');
                Route::post('products/cache', 'ProductController@cacheProducts')->name('cache');
            });

        Route::name('discord.')
            ->prefix('discord')
            ->namespace('Discord')
            ->group(function () {
                Route::post('override', 'DiscordController@overrideUser')->name('override');
                Route::post('modify_guild_permissions', 'DiscordController@modifyGuildPermissions')->name('modify_guild_permission');
                Route::post('{merchant}/configure', 'DiscordController@configureDiscordServer')->name('configure');
                Route::get('redirect', 'DiscordController@redirect')->name('redirect');
                Route::get('access_token_exchange', 'DiscordController@accessTokenExchange')->name('access_token_exchange');
            });

        Route::apiResource('banks', 'BankController');
        Route::put('banks', 'BankController@bulkUpdate')->name('bulk_update');

        Route::apiResource('webhooks', 'WebhookController', [
            'only' => ['index', 'store', 'destroy'],
        ]);

        Route::name('shopify.')
            ->prefix('shopify')
            ->group(function () {
                Route::get('install', 'ShopifyController@installShopify')->name('shopify.install');
                Route::get('generate_token', 'ShopifyController@generateToken')->name('shopify.generate');
                Route::post('update', 'ShopifyController@updateToken')->name('shopify.update');
                Route::post('redirect', 'ShopifyController@redirectToStorefront')->name('shopify.redirect');
                Route::post('webhooks', 'ShopifyController@captureWebhooks')->name('shopify.webhooks');
                Route::post('clean_up', 'ShopifyController@cleanUp');
            });

        Route::post('sendgrid', 'WebhookController@captureSendgridEvent')->name('sendgrid.capture');
        Route::post('viber', 'WebhookController@captureViberEvent')->name('viber.capture');
        Route::post('viber/merchants', 'WebhookController@captureMerchantViberEvent')->name('viber.merchant.capture');

        Route::get('email_domain_name', 'EmailController@checkDNS');

        // Routes for testing

        Route::get('notifications/{notification}', 'NotificationController@show')
            ->name('notifications.show');

        Route::get('webhook_requests', 'WebhookRequestController@index')
            ->name('webhook_requests.index');
    });

Route::name('v2.')
    ->prefix('v2')
    ->namespace('v2')
    ->group(function () {
        Route::apiResource('checkouts', 'CheckoutController')->only('store');
        Route::apiResource('products', 'ProductController')->except('store');

        Route::apiResource('posts', 'PostController')->except('destroy');
        Route::post('posts/{post}', 'PostController@update')->name('posts.post_update');

        Route::name('product_variants.')
            ->prefix('product_variants')
            ->controller('ProductVariantController')
            ->group(function () {
                Route::put('/', 'bulkUpdate')->name('bulk_update');
                Route::get('export', 'export')->name('export');
                Route::post('import', 'import')->name('import');
                Route::post('check_stocks', 'checkStocks')->name('check_stocks');
            });

        Route::apiResource('product_variants', 'ProductVariantController')
            ->only('index', 'show', 'update');

        Route::apiResource('provinces', 'ProvinceController')->only('index', 'show');

        Route::name('orders.')
            ->prefix('orders/{order}')
            ->controller('OrderController')
            ->group(function () {
                Route::put('pay', 'pay')->name('pay');
            });

        Route::apiResource('orders', 'OrderController')->except('store', 'destroy');
        Route::apiResource('order_notifications', 'OrderNotificationController');

        Route::name('subscriptions.')
            ->prefix('subscriptions/{subscription}')
            ->controller('SubscriptionController')
            ->group(function () {
                Route::put('cancel', 'cancel')->name('cancel');
            });

        Route::apiResource('subscriptions', 'SubscriptionController')->only('index', 'store', 'update');

        Route::apiResource('webhooks', 'WebhookController')->except('update');

        Route::name('vimeo.')
            ->prefix('vimeo')
            ->namespace('Vimeo')
            ->group(function () {
                Route::apiResource('videos', 'VideoController')->only('store', 'show');
            });

        // Routes for testing

        Route::get('webhook_requests', 'WebhookRequestController@index')
            ->name('webhook_requests.index');
    });
