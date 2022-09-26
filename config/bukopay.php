<?php

return [
    'ip' => [
        'booking_site' => env('BOOKING_SITE_IP'),
        'storefront' => env('BOOKING_SITE_IP'),
    ],

    'schedule' => [
        'time' => env('SCHEDULE_TIME', '00:00'),
    ],

    'session' => [
        'idle_limit' => env('IDLE_LIMIT', 15)
    ],

    'url' => [
        'merchant_console' => env('MERCHANT_CONSOLE_URL'),
        'checkout' => env('CHECKOUT_URL'),
        'subscription_checkout' => env('SUBSCRIPTION_CHECKOUT_URL'),
        'payment_success' => env('PAYMENT_SUCCESS_URL'),
        'payment_pending' => env('PAYMENT_PENDING_URL'),
        'payment_failed' => env('PAYMENT_FAILED_URL'),
        'skip' => env('SKIP_URL'),
        'cancel' => env('CANCEL_URL'),
        'edit' => env('EDIT_URL'),
        'unsubscribe' => env('UNSUBSCRIBE_URL'),
        'shopify_summary' => env('SHOPIFY_SUMMARY_URL'),
        'deep_link_summary' => env('DEEP_LINK_SUMMARY_URL'),
        'control_panel' => env('CONTROL_PANEL_URL'),
        'script_tag' => env('SHOPIFY_SCRIPT_TAG_URL'),
        'profile' => env('CUSTOMER_PROFILE_URL'),
        'shopify_parse_url' => env('SHOPIFY_PARSE_URL')
    ],
];
