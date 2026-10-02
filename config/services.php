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

    'gallabox' => [
        'api_url' => env('GALLABOX_API_URL'),
        'api_key' => env('GALLABOX_API_KEY'),
        'api_secret' => env('GALLABOX_API_SECRET'),
        'channel_id' => env('GALLABOX_CHANNEL_ID'),
        // Used for /devapi/accounts/{accountId}/... endpoints
        'account_id' => env('GALLABOX_ACCOUNT_ID', env('GALLABOX_WORKSPACE_ID')),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Hub (Aadhaar / PAN / Bank verification)
    |--------------------------------------------------------------------------
    */
    'apihub' => [
        'base_url' => rtrim(env('APIHUB_BASE_URL', 'http://apihub.services'), '/'),
        'client_id' => env('APIHUB_CLIENT_API_ID', env('APIHUB_API_KEY', env('API_KEY'))),
        'client_secret' => env('APIHUB_CLIENT_API_SECRET', env('APIHUB_API_SECRET', env('SECRET_KEY'))),
        'mode' => env('APIHUB_API_MODE', env('MODE', 'production')),
        'use_sandbox' => filter_var(env('APIHUB_USE_SANDBOX', false), FILTER_VALIDATE_BOOLEAN),
        'aadhaar_send' => env('APIHUB_AADHAAR_SEND_PATH', 'v5/okyc/inititate'),
        'aadhaar_verify' => env('APIHUB_AADHAAR_VERIFY_PATH', 'v5/okyc/verify'),
        'pan_path' => env('APIHUB_PAN_PATH', 'v1/pan'),
        'bank_path' => env('APIHUB_BANK_PATH', 'v4/bank-hybrid'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MSG91 SMS / OTP
    |--------------------------------------------------------------------------
    */
    'msg91' => [
        'auth_key' => env('MSG91_AUTH_KEY'),
        'base_url' => rtrim(env('MSG91_BASE_URL', 'https://control.msg91.com/api/v5'), '/'),
        'otp_template_id' => env('MSG91_OTP_TEMPLATE_ID', '69ef6a68ccafa7555605e383'),
        'otp_identifier' => env('MSG91_OTP_IDENTIFIER', 'basic_otp'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'fintronixmicrofinance-c2656'),
        'credentials' => env(
            'FIREBASE_CREDENTIALS',
            'storage/app/firebase/fintronixmicrofinance-c2656-firebase-adminsdk-fbsvc-7d456d1b6f.json'
        ),
    ],

];
