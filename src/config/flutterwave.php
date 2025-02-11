<?php

declare(strict_types=1);

use Flutterwave\Payments\Data\Currency;
use Flutterwave\Payments\Services\Modal;
use Flutterwave\Payments\Services\Transactions;
use Flutterwave\Payments\Services\Webhooks;

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
     | This is the list of services that are available for use in the package.
     | You can add or remove a service.
     */
    'services' => [
        'transactions' => Transactions::class,
        'webhooks' => Webhooks::class,
        'modals' => Modal::class,
    ],

    'paths' => [
        'logs' => storage_path('flutterwave/log')
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

    'redirectUrl' => env('FLW_REDIRECT_URL', env('APP_URL') . '/flutterwave/payment/callback'),

    'successUrl' => env('FLW_SUCCESS_URL', env('APP_URL') . '/flutterwave/payment/success'),

    'cancelUrl' => env('FLW_CANCEL_URL', env('APP_URL') . '/flutterwave/payment/cancel'),
];
