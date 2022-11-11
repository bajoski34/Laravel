<p align="center">
    <img title="Flutterwave" height="200" src="https://flutterwave.com/images/logo/full.svg" width="50%"/>
</p>

# Flutterwave Laravel.

![Packagist Downloads](https://img.shields.io/packagist/dt/flutterwavedev/flutterwave-v3)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/flutterwavedev/flutterwave-v3)
![GitHub stars](https://img.shields.io/github/stars/Flutterwave/Flutterwave-PHP-v3)
![Packagist License](https://img.shields.io/packagist/l/flutterwavedev/flutterwave-v3)

This Flutterwave Laravel Package provides easy access to Flutterwave for Business (F4B) v3 APIs from Laravel apps. It abstracts the complexity involved in direct integration and allows you to make quick calls to the APIs.

Available features include:

- Collections: Card, Account, Mobile money, Bank Transfers, USSD, Barter, NQR.

## Table of Contents
1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Initialization](#initialization)
4. [Usage](#usage)
5. [Testing](#testing)
6. [Debugging Errors](#debugging-errors)
7. [Support](#support)
8. [Contribution guidelines](#contribution-guidelines)
9. [License](#license)
10. [Changelog](#changelog)

<a id="requirements"></a>

## Requirements

1. Flutterwave for business [API Keys](https://developer.flutterwave.com/docs/integration-guides/authentication)
2. Acceptable PHP versions: >= 7.3


<a id="installation"></a>

## Installation

The vendor folder is committed into the project to allow easy installation for those who do not have composer installed.
It is recommended to update the project dependencies using:

```shell
$ composer require flutterwavedev/laravel-payment
```

Ensure that you publish your config file by running:

```shell
$ php artisan vendor:publish --provider="Flutterwave\Payments\Providers\PackageServiceProvider"   
```


<a id="initialization"></a>

## Initialization

In your .env file add the following environment variables:

```env

PUBLIC_KEY="****YOUR**PUBLIC**KEY****" // can be gotten from the dashboard
SECRET_KEY="****YOUR**SECRET**KEY****" // can be gotten from the dashboard
ENCRYPTION_KEY="Encryption key"
ENV="staging/production"

```

Business Settings/preferences like logo, name, payment method can be set in the config file `config/flutterwave.php`

```php
'businessName' => env('BUSINESS_NAME', 'Flutterwave Store'),
'transactionPrefix' => env('TRANSACTION_PREFIX', 'LARAVEL-'),
'logo' => env('BUSINESS_LOGO', 'https://res.cloudinary.com/decagon/image/upload/v1593642339/decagon-logo.png'),
'title' => env('TITLE', 'Flutterwave Store'),
'description' => env('DESCRIPTION', 'Flutterwave Store Description'),
'country' => env('COUNTRY', 'NG'),
'currency' => env('CURRENCY', Currency::NGN),
'paymentType' => [
    'card', 'account', 'banktransfer', 'mpesa', 'mobilemoneyrwanda', 'mobilemoneyzambia',
    'mobilemoneyuganda', 'ussd', 'qr', 'mobilemoneyghana', 'credit', 'barter',
    'payattitude', 'mobilemoneyfranco', 'mobilemoneytanzania', 'paga', '1voucher',
],
```

<a id="usage"></a>

## Usage

### Render Payment Modal
There are two types of modal that can be rendered, the inline modal and the standard modal. The inline modal is rendered on your website while the standard modal is rendered on a flutterwave hosted page.

## Inline Modal
```php
$payload = [
    "tx_ref" => "MC-".time(),
    "amount" => 100,
    "currency" => Currency::NGN,
    "customer" => [
        "email" => "olaobajua@gmail.com"
    ],
];


$service = new Flutterwave\Payments\Flutterwave();

if (! isset($payload['tx_ref'])) {
    $payload['tx_ref'] = $service->generateTransactionReference();
}

$payment_details = $service->render('inline', $payload);

return view('flutterwave::modal', compact('payment_details'));

```

## Standard Modal
```php
$service = new Flutterwave\Payments\Flutterwave();

if (! isset($payload['tx_ref'])) {
    $payload['tx_ref'] = $service->generateTransactionReference();
}

$payment_link = $service->render('standard', $payload);

return redirect($payment_link);

```

<br>





## Testing

All of the SDK's tests are written with PHP's ```phpunit``` module. The tests currently test:
```Modals```,
```Webhooks```,
```Transactions```,

They can be run like so:

```sh
phpunit
```

>**NOTE:** If the test fails for creating a subaccount, just change the ```account_number``` ```account_bank```  and ```businesss_email``` to something different

>**NOTE:** The test may fail for account validation - ``` Pending OTP validation``` depending on whether the service is down or not
<br>


<a id="debugging errors"></a>

## Debugging Errors
We understand that you may run into some errors while integrating our library. You can read more about our error messages [here](https://developer.flutterwave.com/docs/integration-guides/errors).

For `authorization` and `validation` error responses, double-check your API keys and request. If you get a `server` error, kindly engage the team for support.


<a id="support"></a>

## Support
For additional assistance using this library, contact the developer experience (DX) team via [email](mailto:developers@flutterwavego.com) or on [slack](https://bit.ly/34Vkzcg).

You can also follow us [@FlutterwaveEng](https://twitter.com/FlutterwaveEng) and let us know what you think 😊.


<a id="contribution-guidelines"></a>

## Contribution guidelines
Read more about our community contribution guidelines [here](/CONTRIBUTING.md)

<a id="license"></a>

## License

By contributing to this library, you agree that your contributions will be licensed under its [MIT license](/LICENSE).

Copyright (c) Flutterwave Inc.


<a id="references"></a>

## Flutterwave API  References

- [Flutterwave API Documentation](https://developer.flutterwave.com)
- [Flutterwave Dashboard](https://app.flutterwave.com)  
