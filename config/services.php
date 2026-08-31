<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'lemonsqueezy' => [
        'api_key' => env('LEMON_SQUEEZY_API_KEY'),
        'store_id' => env('LEMON_SQUEEZY_STORE_ID', '463287'),
        'signing_secret' => env('LEMON_SQUEEZY_SIGNING_SECRET'),
        'variants' => [
            'starter' => env('LEMON_SQUEEZY_STARTER_SUBSCRIPTION', env('LEMON_SQUEEZY_VARIANT_STARTER', '2076021')),
            'starter_onetime' => env('LEMON_SQUEEZY_STARTER_ONE_TIME', '2076019'),
            'pro' => env('LEMON_SQUEEZY_PRO_SUBSCRIPTION', env('LEMON_SQUEEZY_VARIANT_PRO', '2076026')),
            'pro_onetime' => env('LEMON_SQUEEZY_PRO_ONE_TIME', '2076025'),
            'agency' => env('LEMON_SQUEEZY_AGENCY_SUBSCRIPTION', env('LEMON_SQUEEZY_VARIANT_AGENCY', '2076033')),
            'agency_onetime' => env('LEMON_SQUEEZY_AGENCY_ONE_TIME', '2076032'),
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
