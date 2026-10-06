<?php

declare(strict_types=1);

use Flutterwave\Payments\Data\Currency;

return [
    /*
     |--------------------------------------------------------------------------
     | API Keys [DO NOT EDIT SECTION] [DON'T EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | This is where you can specify your Flutterwave API keys and other settings.
     */

    'publicKey' => env('FLW_PUBLIC_KEY'),
    'secretKey' => env('FLW_SECRET_KEY'),

    /*
     |--------------------------------------------------------------------------
     | Flutterwave Services [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | Every service ships enabled. To swap one for your own class, map its
     | key here, e.g. 'transfers' => App\Payments\MyTransfers::class.
     | Set a key to null to disable that service.
     */
    'services' => [],

    /*
     |--------------------------------------------------------------------------
     | HTTP Client [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | Request timeout in seconds, and how many times GET requests are retried
     | when Flutterwave cannot be reached. POST requests are never retried.
     */
    'timeout' => (int) env('FLW_TIMEOUT', 60),
    'retries' => (int) env('FLW_RETRIES', 2),

    /*
     |--------------------------------------------------------------------------
     | Webhooks [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | The package registers POST /{path} for Flutterwave webhooks, checks the
     | signature against FLW_SECRET_HASH and dispatches Laravel events
     | (WebhookReceived, ChargeCompleted, TransferCompleted, SubscriptionCancelled).
     |
     | verify_charges re-fetches every charge.completed transaction from the API
     | before ChargeCompleted fires, so you never act on a forged payload.
     */
    'webhook' => [
        'enabled' => env('FLW_WEBHOOK_ENABLED', true),
        'path' => env('FLW_WEBHOOK_PATH', 'flutterwave/webhook'),
        'middleware' => [],
        'verify_charges' => true,
    ],

    'paths' => [
        'logs' => storage_path('flutterwave/log'),
    ],
    /*
     |--------------------------------------------------------------------------
     | Secret Hash [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | The secret hash allows you to verify that incoming requests are from
     | Flutterwave.
     */

    'secretHash' => env('FLW_SECRET_HASH', ''),

    /*
     |--------------------------------------------------------------------------
     | Encryption Key [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | The encryption key is used to automatically encrypt specific payloads
     | before sending them to Flutterwave.
     */

    'encryptionKey' => env('FLW_ENCRYPTION_KEY', ''),

    /*
     |--------------------------------------------------------------------------
     | Environment [DO NOT EDIT SECTION] [DON'T EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | This is where you can specify your Flutterwave API keys and other settings.
     */

    'env' => env('FLW_ENVIRONMENT', 'staging'),

    /*
     |--------------------------------------------------------------------------
     | Business Details [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     |
     | set your business name, logo, country and currency defaults
     |
     */
    'merchantId' => env('FLW_MERCHANT_ID'),
    'businessName' => env('FLW_BUSINESS_NAME', 'Flutterwave Store'),
    'transactionPrefix' => env('FLW_TRANSACTION_PREFIX', 'LARAVEL-'),
    'logo' => env('FLW_BUSINESS_LOGO', 'https://avatars.githubusercontent.com/u/39011309?v=4'),
    'title' => env('FLW_PAYMENT_DESCRIPTOR', 'Flutterwave Store'),
    'description' => env('FLW_CHECKOUT_DESCRIPTION', 'Flutterwave Store Description'),
    'country' => env('FLW_DEFAULT_COUNTRY', 'NG'),
    'currency' => env('FLW_DEFAULT_CURRENCY', Currency::NGN),
    'paymentType' => [
        'card',
        'account',
        'banktransfer',
        'mpesa',
        'mobilemoneyrwanda',
        'mobilemoneyzambia',
        'mobilemoneyuganda',
        'ussd',
        'qr',
        'mobilemoneyghana',
        'credit',
        'barter',
        'payattitude',
        'mobilemoneyfranco',
        'mobilemoneytanzania',
        'paga',
        '1voucher',
    ],

    /*
     |--------------------------------------------------------------------------
     | Application Settings [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     |
     | set your application settings
     |
     */

    'redirectUrl' => env('FLW_REDIRECT_URL', env('APP_URL').'/flutterwave/payment/callback'),

    'successUrl' => env('FLW_SUCCESS_URL', env('APP_URL').'/flutterwave/payment/success'),

    'cancelUrl' => env('FLW_CANCEL_URL', env('APP_URL').'/flutterwave/payment/cancel'),
];
