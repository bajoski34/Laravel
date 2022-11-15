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

    'publicKey' => env('PUBLIC_KEY'),
    'secretKey' => env('SECRET_KEY'),

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

    /*
     |--------------------------------------------------------------------------
     | Secret Hash [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | The secret hash allows you to verify that incoming requests are from
     | Flutterwave.
     */

    'secretHash' => env('SECRET_HASH', ''),

    /*
     |--------------------------------------------------------------------------
     | Encryption Key [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | The encryption key is used to automatically encrypt specific payloads
     | before sending them to Flutterwave.
     */

    'encryptionKey' => env('ENCRYPTION_KEY', ''),

    /*
     |--------------------------------------------------------------------------
     | Environment [DO NOT EDIT SECTION] [DON'T EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     | This is where you can specify your Flutterwave API keys and other settings.
     */

    'env' => env('FLUTTERWAVE_ENV', 'staging'),

    /*
     |--------------------------------------------------------------------------
     | Business Details [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     |
     | set your business name, logo, country and currency defaults
     |
     */
    'businessName' => env('BUSINESS_NAME', 'Flutterwave Store'),
    'transactionPrefix' => env('TRANSACTION_PREFIX', 'LARAVEL-'),
    'logo' => env('BUSINESS_LOGO', 'https://avatars.githubusercontent.com/u/39011309?v=4'),
    'title' => env('TITLE', 'Flutterwave Store'),
    'description' => env('DESCRIPTION', 'Flutterwave Store Description'),
    'country' => env('COUNTRY', 'NG'),
    'currency' => env('CURRENCY', Currency::NGN),
    'paymentType' => [
        'card', 'account', 'banktransfer', 'mpesa', 'mobilemoneyrwanda', 'mobilemoneyzambia',
        'mobilemoneyuganda', 'ussd', 'qr', 'mobilemoneyghana', 'credit', 'barter',
        'payattitude', 'mobilemoneyfranco', 'mobilemoneytanzania', 'paga', '1voucher',
    ],

    /*
     |--------------------------------------------------------------------------
     | Application Settings [YOU CAN EDIT THIS SECTION]
     |--------------------------------------------------------------------------
     |
     | set your application settings
     |
     */

    'redirectUrl' => env('REDIRECT_URL', env('APP_URL').'/flutterwave/payment/callback'),

    'successUrl' => env('SUCCESS_URL', env('APP_URL').'/flutterwave/payment/success'),

    'cancelUrl' => env('CANCEL_URL', env('APP_URL').'/flutterwave/payment/cancel'),
];
