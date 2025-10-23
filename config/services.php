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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shodan' => [
        'api_key' => env('SHODAN_API_KEY'),
        'base_url' => env('SHODAN_BASE_URL', 'https://api.shodan.io'),
    ],

    'nvd' => [
        'endpoint' => env('NVD_API_ENDPOINT', 'https://services.nvd.nist.gov/rest/json/cves/2.0'),
        'api_key' => env('NVD_API_KEY'),
        'delay_seconds' => (int) env('CVE_UPDATE_DELAY_SECONDS', 6), // 6s public, 1s with API key
        'batch_size' => (int) env('CVE_BATCH_SIZE', 5),
    ],

];
