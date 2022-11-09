<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Flutterwave Settings
     |--------------------------------------------------------------------------
     |
     | This is where you can specify your Flutterwave API keys and other settings.
     |
     */

    'publicKey' => env('PUBLIC_KEY'),

    'secretKey' => env('SECRET_KEY'),

    'secretHash' => env('SECRET_HASH', ''),

    'encryptionKey' => env('ENCRYPTION_KEY', ''),

    'env' => env('FLUTTERWAVE_ENV', 'staging'),

    /*
     |--------------------------------------------------------------------------
     | Business Settings
     |--------------------------------------------------------------------------
     |
     | This is where you can specify your business settings.
     |
     */

    'businessName' => env('BUSINESS_NAME', 'Flutterwave Store'),

    'logo' => env('BUSINESS_LOGO', 'https://res.cloudinary.com/decagon/image/upload/v1593642339/decagon-logo.png'),

    'title' => env('TITLE', 'Flutterwave Store'),

    'country' => env('COUNTRY', 'NG'),

    'description' => env('DESCRIPTION', 'Flutterwave Store Description'),

    /*
     |--------------------------------------------------------------------------
     | Application Settings
     |--------------------------------------------------------------------------
     |
     | This is where you can specify your application settings.
     |
     */

    'redirectUrl' => env('REDIRECT_URL', env('APP_URL').'/flutterwave/payment/callback'),

    'successUrl' => env('SUCCESS_URL', env('APP_URL').'/flutterwave/payment/success'),

    'cancelUrl' => env('CANCEL_URL', env('APP_URL').'/flutterwave/payment/cancel'),
];
