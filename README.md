<p align="center">
    <img title="Flutterwave" height="200" src="https://flutterwave.com/images/logo/full.svg" width="50%"/>
</p>

# Flutterwave Laravel.

![Packagist Downloads](https://img.shields.io/packagist/dt/abraham-flutterwave/laravel-payment)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/abraham-flutterwave/laravel-payment)
![GitHub stars](https://img.shields.io/github/stars/bajoski34/Laravel)
![Packagist License](https://img.shields.io/packagist/l/abraham-flutterwave/laravel-payment)

This Flutterwave Laravel Package provides easy access to Flutterwave for Business (F4B) v3 APIs from Laravel apps. It abstracts the complexity involved in direct integration and allows you to make quick calls to the APIs.

Available features include:

- Collections: Card, Account, Mobile money, Bank Transfers, USSD, Barter, NQR.
- Transfers: Bank transfers, mobile money transfers, balance management.
- Cards: Card charging, tokenization, preauthorization, BIN lookup.
- Subaccounts: Manage subaccounts and payout subaccounts.
- Plans: Payment plans and subscription management.
- Mobile Money: Comprehensive mobile money support across African countries.
- Console Commands: Artisan commands for payments, webhooks, and refunds.

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
$ composer require abraham-flutterwave/laravel-payment
```

Ensure that you publish your config file by running:

```shell
$ php artisan vendor:publish --provider="Flutterwave\Payments\Providers\FlutterwaveServiceProvider"  
```


<a id="initialization"></a>

## Initialization

In your .env file add the following environment variables:

```env
FLW_PUBLIC_KEY="****YOUR**PUBLIC**KEY****" // can be gotten from the dashboard
FLW_SECRET_KEY="****YOUR**SECRET**KEY****" // can be gotten from the dashboard
FLW_ENCRYPTION_KEY="Encryption key"
FLW_ENVIRONMENT="development"

```

Business Settings/preferences like logo, name, payment method can be set in the config file `config/flutterwave.php`

```php
'businessName' => env('FLW_BUSINESS_NAME', 'Flutterwave Store'),
'transactionPrefix' => env('FLW_TRANSACTION_PREFIX', 'LARAVEL-'),
'logo' => env('FLW_BUSINESS_LOGO', 'https://avatars.githubusercontent.com/u/39011309?v=4'),
'title' => env('FLW_PAYMENT_DESCRIPTOR', 'Flutterwave Store'),
'description' => env('FLW_CHECKOUT_DESCRIPTION', 'Flutterwave Store Description'),
'country' => env('FLW_DEFAULT_COUNTRY', 'NG'),
'currency' => env('FLW_DEFAULT_CURRENCY', Currency::NGN),
'paymentType' => [
    'card', 'account', 'banktransfer', 'mpesa', 'mobilemoneyrwanda', 'mobilemoneyzambia',
    'mobilemoneyuganda', 'ussd', 'qr', 'mobilemoneyghana', 'credit', 'barter',
    'payattitude', 'mobilemoneyfranco', 'mobilemoneytanzania', 'paga', '1voucher',
],
```

<a id="usage"></a>

## Usage

### Quick Payment Creation

The simplest way to create a payment using the new helper method:

```php
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Data\Currency;

// Create a quick payment
$paymentLink = Flutterwave::createPayment(
    amount: 1000,
    currency: Currency::NGN,
    email: 'customer@example.com',
    customerName: 'John Doe',
    customerPhone: '+2348123456789'
);

return redirect($paymentLink);
```

### Using Data Transfer Objects

For better type safety and structure:

```php
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Data\DTO\Customer;
use Flutterwave\Payments\Data\DTO\PaymentRequest;
use Flutterwave\Payments\Data\Currency;

$customer = Customer::make('customer@example.com', 'John Doe', '+2348123456789');
$payment = PaymentRequest::make(
    Flutterwave::generateTransactionReference(),
    1000,
    Currency::NGN,
    $customer
)->withMeta(['order_id' => '12345']);

$paymentLink = Flutterwave::render('standard', $payment->toArray());
```

## Service Usage

### Transfers Service

Handle bank transfers and mobile money transfers:

```php
use Flutterwave\Payments\Facades\Flutterwave;

$transfers = Flutterwave::transfers();

// Create bank transfer
$bankTransfer = $transfers->bank([
    'account_bank' => '044',
    'account_number' => '0690000040',
    'amount' => 5000,
    'currency' => 'NGN',
    'beneficiary_name' => 'John Doe',
    'reference' => 'transfer_ref_123',
    'narration' => 'Payment for services',
]);

// Get transfer fee
$fee = $transfers->fee([
    'amount' => 5000,
    'currency' => 'NGN',
    'type' => 'account'
]);

// Check wallet balance
$balance = $transfers->walletBalance('NGN');
```

### Cards Service

Handle card operations with security features:

```php
use Flutterwave\Payments\Facades\Flutterwave;

$cards = Flutterwave::cards();

// Charge a card
$charge = $cards->charge([
    'card_number' => '5531886652142950',
    'cvv' => '564',
    'expiry_month' => '09',
    'expiry_year' => '32',
    'currency' => 'NGN',
    'amount' => 1000,
    'email' => 'user@example.com',
    'tx_ref' => 'MC-3243e',
]);

// Tokenize a card for future use
$token = $cards->tokenize([
    'card_number' => '5531886652142950',
    'cvv' => '564',
    'expiry_month' => '09',
    'expiry_year' => '32',
    'email' => 'user@example.com',
]);

// Charge with token
$chargeWithToken = $cards->chargeWithToken([
    'token' => 'flw-t1nf-token',
    'currency' => 'NGN',
    'amount' => 1000,
    'email' => 'user@example.com',
    'tx_ref' => 'MC-3243e',
]);

// Get card BIN information
$binInfo = $cards->binLookup('553188');
```

### Subaccounts Service

Manage subaccounts and payout subaccounts:

```php
use Flutterwave\Payments\Facades\Flutterwave;

$subaccounts = Flutterwave::subaccounts();

// Create subaccount
$subaccount = $subaccounts->create([
    'account_bank' => '044',
    'account_number' => '0690000040',
    'business_name' => 'John Doe Store',
    'business_email' => 'john@example.com',
    'business_contact' => 'John Doe',
    'business_contact_mobile' => '+2348123456789',
    'business_mobile' => '+2348123456789',
    'country' => 'NG',
    'split_type' => 'percentage',
    'split_value' => 0.5,
]);

// Get available banks
$banks = $subaccounts->getBanks('NG');

// Validate bank account
$validation = $subaccounts->validateBankAccount([
    'account_number' => '0690000040',
    'account_bank' => '044'
]);
```

### Plans Service

Manage payment plans and subscriptions:

```php
use Flutterwave\Payments\Facades\Flutterwave;

$plans = Flutterwave::plans();

// Create payment plan
$plan = $plans->create([
    'amount' => 5000,
    'name' => 'Monthly Subscription',
    'interval' => 'monthly',
    'duration' => 12,
    'currency' => 'NGN',
]);

// Create subscription
$subscription = $plans->subscribe([
    'tx_ref' => 'subscription_ref_123',
    'amount' => 5000,
    'currency' => 'NGN',
    'payment_plan' => $plan['data']['id'],
    'customer' => [
        'email' => 'customer@example.com',
        'name' => 'John Doe',
    ],
]);

// Generate subscription link
$link = $plans->generateLink([
    'tx_ref' => 'subscription_link_123',
    'amount' => 5000,
    'currency' => 'NGN',
    'payment_plan' => $plan['data']['id'],
    'customer' => [
        'email' => 'customer@example.com',
    ],
]);
```

### Mobile Money Service

Handle mobile money payments across African countries:

```php
use Flutterwave\Payments\Facades\Flutterwave;

$mobileMoney = Flutterwave::mobileMoney();

// Ghana Mobile Money
$ghanaPayment = $mobileMoney->ghana([
    'tx_ref' => 'momo_ref_123',
    'amount' => 100,
    'currency' => 'GHS',
    'network' => 'MTN',
    'phone_number' => '233245123456',
    'email' => 'customer@example.com',
]);

// Kenya M-Pesa
$mpesa = $mobileMoney->kenya([
    'tx_ref' => 'mpesa_ref_123',
    'amount' => 1000,
    'currency' => 'KES',
    'phone_number' => '254712345678',
    'email' => 'customer@example.com',
]);

// Validate phone number for country
$isValid = $mobileMoney->validatePhoneNumber('+233245123456', 'GH');

// Format phone number
$formatted = $mobileMoney->formatPhoneNumber('0245123456', 'GH'); // Returns: 233245123456

// Get available networks
$networks = $mobileMoney->getNetworks('GH'); // ['mtn', 'vodafone', 'airteltigo']
```

## Console Commands

The package now includes Artisan commands for common operations:

### Generate Payment Link

```bash
# Basic payment
php artisan flutterwave:payment 1000 customer@example.com

# With options
php artisan flutterwave:payment 1000 customer@example.com \
    --currency=USD \
    --customer-name="John Doe" \
    --customer-phone="+2348123456789" \
    --type=inline
```

### Verify Webhook

```bash
php artisan flutterwave:verify-webhook '{"data":{"tx_ref":"test_ref"}}' "signature_hash"
```

### Refund Transaction

```bash
# Full refund
php artisan flutterwave:refund 12345 --verify

# Partial refund
php artisan flutterwave:refund 12345 --amount=500 --verify
```

## Render Payment Modal
There are two types of modal that can be rendered, the inline modal and the standard modal. The inline modal is rendered on your website while the standard modal is rendered on a flutterwave hosted page.

### Inline Modal
```php
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Data\Currency;

$payload = [
    "tx_ref" => Flutterwave::generateTransactionReference(),
    "amount" => 100,
    "currency" => Currency::NGN,
    "customer" => [
        "email" => "developers@flutterwavego.com"
    ],
];

$payment_details = Flutterwave::render('inline', $payload);

return view('flutterwave::modal', compact('payment_details'));

```

### Standard Modal
```php
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Data\Currency;

$payload = [
    "tx_ref" => Flutterwave::generateTransactionReference(),
    "amount" => 100,
    "currency" => Currency::NGN,
    "customer" => [
        "email" => "developers@flutterwavego.com"
    ],
];

$payment_link = Flutterwave::render('standard', $payload);

return redirect($payment_link);
```

These are the routes available for integrating the Flutterwave payment system. Below is a breakdown of each route and its purpose.

### Checkout Route
URL: /flutterwave/payment/checkout

Method: POST (form-data)

Description: This route initiates the payment checkout process. The user will be required to send a POST request with the necessary payment details such as amount, currency, and email. If additional meta data is provided, it will be included in the request.

#### Parameters:

- amount (required): The payment amount.
- currency (required): The currency code (e.g., USD, NGN).
- email (required): The email address of the customer.
- meta (optional): Any custom data related to the payment.
Response: Returns a view (flutterwave::modal) that contains the payment details and a Flutterwave inline payment form.

### Payment Callback Route
URL: /flutterwave/payment/callback

Method: GET

Description: This route handles the callback from Flutterwave after a payment attempt. It verifies the transaction status using the transaction reference (tx_ref). Based on the result, it redirects the user to appropriate pages.

#### Parameters:

- tx_ref (required): The transaction reference ID returned by Flutterwave.
Response:

If the transaction is successful, the user is redirected to the success page (flutterwave.successful).
If the transaction is pending, it may redirect the user to a page that will poll for the transaction's final status.
If the transaction has failed, the user is redirected to the failure page (flutterwave.failed).
3. Payment Success Route
URL: /flutterwave/payment/success

Method: GET

Description: This route is called when a payment is successfully completed.

Response: Returns a simple message, "Payment Successful".

### Payment Failed Route
URL: /flutterwave/payment/failed

Method: GET

Description: This route is called when a payment fails.

Response: Returns a simple message, "Payment Failed".

### Payment Cancelled Route
URL: /flutterwave/payment/cancel

Method: GET

Description: This route is called when a payment is cancelled by the user.

Response: Returns a simple message, "Payment Cancelled".

<br>

## Logging

To enable logging, simple add the following to your config file `config/logging.php`

```php
'flutterwave' => [
    'driver' => 'single',
    'path' => storage_path('logs/flutterwave.log'),
    'level' => 'debug',
],

```

## Webhook Setup

Create a Webhook url to receive payment notification on Payment events.
Below is a sample of a webhook url implementation using the new package.
```php
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Data\Status;

Route::post('flutterwave/payment/webhook', function () {
    $method = request()->method();
    if ($method === 'POST') {
        //get the request body
        $body = request()->getContent();
        $webhook = Flutterwave::use('webhooks');
        $transaction = Flutterwave::use('transactions');
        //get the request signature
        $signature = request()->header($webhook::SECURE_HEADER);

        //verify the signature
        $isVerified = $webhook->verifySignature($body, $signature);

        if ($isVerified) {
            [ 'tx_ref' => $tx_ref, 'id' => $id ] = $webhook->getHook();
            [ 'status' => $status, 'data' => $transactionData ] = $transaction->verifyTransactionReference($tx_ref);

            $responseData = ['tx_ref' => $tx_ref, 'id' => $id];
            if ($status === 'success') {
                switch ($transactionData['status']) {
                    case Status::SUCCESSFUL:
                        // do something
                        //save to database
                        //send email
                        break;
                    case Status::PENDING:
                        // do something
                        //save to database
                        //send email
                        break;
                    case Status::FAILED:
                        // do something
                        //save to database
                        //send email
                        break;
                }
            }

            return response()->json(['status' => 'success', 'message' => 'Webhook verified by Flutterwave Laravel Package', 'data' => $responseData]);
        }

        return response()->json(['status' => 'error', 'message' => 'Access denied. Hash invalid'])->setStatusCode(401);
    }

    // return 404
    return abort(404);
})->name('flutterwave.webhook');
```

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

## TODOs
1. ~~Add other Flutterwave Services - card,transfer,subaccount,payoutsubaccounts,plans and momo~~ ✅ **COMPLETED**
2. ~~Console Commands - Webhooks, Make Payment, and Refunds.~~ ✅ **COMPLETED**
3. Add comprehensive test coverage for new services
4. Add payment link generation service
5. Add dispute management service
6. Add settlement management service
