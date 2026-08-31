<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Lemon Squeezy (merchant of record)
    |--------------------------------------------------------------------------
    |
    | Every store, product and variant identifier comes from the environment.
    | They were previously inlined as literals here and in the webhook handler,
    | which meant renaming a product in Lemon Squeezy silently reassigned
    | customers to the wrong tier.
    |
    */

    'lemonsqueezy' => [
        'api_key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID'),
        'store_url' => env('LEMON_SQUEEZY_STORE_URL', 'https://omnisignal.lemonsqueezy.com'),
        'signing_secret' => env('LEMON_SQUEEZY_SIGNING_SECRET'),

        'variants' => [
            'starter' => env('LEMON_SQUEEZY_STARTER_SUBSCRIPTION'),
            'starter_onetime' => env('LEMON_SQUEEZY_STARTER_ONE_TIME'),
            'pro' => env('LEMON_SQUEEZY_PRO_SUBSCRIPTION'),
            'pro_onetime' => env('LEMON_SQUEEZY_PRO_ONE_TIME'),
            'agency' => env('LEMON_SQUEEZY_AGENCY_SUBSCRIPTION'),
            'agency_onetime' => env('LEMON_SQUEEZY_AGENCY_ONE_TIME'),
        ],

        // Optional: used as a fallback when an order carries a product ID but
        // no recognised variant.
        'products' => [
            'starter' => env('LEMON_SQUEEZY_STARTER_PRODUCT'),
            'pro' => env('LEMON_SQUEEZY_PRO_PRODUCT'),
            'agency' => env('LEMON_SQUEEZY_AGENCY_PRODUCT'),
        ],
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
