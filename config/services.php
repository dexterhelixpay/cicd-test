<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'brankas' => [
        'api_url' => env('BRANKAS_API_URL'),
        'api_key' => env('BRANKAS_API_KEY'),
        'destination_account_id' => env('BRANKAS_DESTINATION_ACCOUNT_ID'),
        'destination_bank_code' => env('BRANKAS_DESTINATION_BANK_CODE')
    ],

    'cloudflare' => [
        'api_url' => env('CLOUDFLARE_API_URL'),
        'auth_email' => env('CLOUDFLARE_AUTH_EMAIL'),
        'auth_key' => env('CLOUDFLARE_AUTH_KEY'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],

    'globelabs' => [
        'shortcode' => env('GLOBELABS_SHORT_CODE'),
        'id' => env('GLOBELABS_APP_ID'),
        'secret' => env('GLOBELABS_APP_SECRET'),
        'passphrase' => env('GLOBELABS_PASSPHRASE'),
    ],

    'm360' => [
        'api_url' => env('M360_API_URL'),
        'username' => env('M360_USERNAME'),
        'password' => env('M360_PASSWORD'),
        'shortcode_mask' => env('M360_SHORTCODE_MASK'),

        // Deprecated
        'passphrase' => env('M360_CLIENT_PASSPHRASE'),
        'sender_address' => env('M360_SENDER_ADDRESS'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'paymaya' => [
        'api_url' => env('PAYMAYA_API_URL'),
        'pwp' => [
            'public_key' => env('PAYMAYA_PWP_PUBLIC_KEY'),
            'secret_key' => env('PAYMAYA_PWP_SECRET_KEY'),
        ],
        'vault' => [
            'public_key' => env('PAYMAYA_VAULT_PUBLIC_KEY'),
            'secret_key' => env('PAYMAYA_VAULT_SECRET_KEY'),
        ],
        'ips' => env_array('PAYMAYA_IPS', []),
        'metadata' => [
            'mci' => env('PAYMAYA_METADATA_MCI', 'NCR'),
            'mco' => env('PAYMAYA_METADATA_MCO', 'PHL'),
            'mpc' => env('PAYMAYA_METADATA_MPC', 608),
            'smi' => [
                'visa' => env('PAYMAYA_METADATA_SMI_VISA'),
                'mastercard' => env('PAYMAYA_METADATA_SMI_MASTERCARD'),
                'jcb' => env('PAYMAYA_METADATA_SMI_JCB'),
            ],
        ],
    ],

    'paymongo' => [
        'api_url' => env('PAYMONGO_API_URL'),
        'api_version' => env('PAYMONGO_API_VERSION'),
        'public_key' => env('PAYMONGO_PUBLIC_KEY'),
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
    ],

    'pesopay' => [
        'api_url' => env('PESOPAY_API_URL'),
        'merchant_id' => env('PESOPAY_MERCHANT_ID'),
        'secure_hash_secret' => env('PESOPAY_SECURE_HASH_SECRET'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sendgrid' => [
        'key' => env('SENDGRID_API_KEY'),
        'verification_key' => env('SENDGRID_VERIFICATION_KEY'),
    ],

    'slack' => [
        'sms_webhook_url' => env('SLACK_SMS_WEBHOOK_URL'),
    ],

    'shopify' => [
        'api_key' => env('SHOPIFY_PARTNER_API_KEY'),
        'secret_key' => env('SHOPIFY_PARTNER_SECRET_KEY'),
    ],

    'viber' => [
        'api_url' => env('VIBER_API_URL'),
        'auth_token' => env('VIBER_SECRET_KEY'),
        'merchant_auth_token' => env('VIBER_MERCHANT_SECRET_KEY'),
        'sender_name' => env('VIBER_SENDER_NAME'),
        'sender_avatar' => env('VIBER_SENDER_AVATAR'),
    ],

    'xendit' => [
        'api_url' => env('XENDIT_API_URL'),
        'public_key' => env('XENDIT_PUBLIC_KEY'),
        'secret_key' => env('XENDIT_SECRET_KEY'),
    ],

    'discord' => [
        'api_url' => env('DISCORD_API_URL'),
        'redirect_url' => env('DISCORD_REDIRECT_URL'),
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'bot_token' => env('DISCORD_BOT_TOKEN')
    ],

    'vimeo' => [
        'api_url' => env('VIMEO_API_URL', 'https://api.vimeo.com'),
        'client_id' => env('VIMEO_CLIENT_ID'),
        'client_secret' => env('VIMEO_CLIENT_SECRET'),
        'access_token' => env('VIMEO_ACCESS_TOKEN'),
    ],
];
