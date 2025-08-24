<?php

use Flutterwave\Payments\Data\DTO\Customer;
use Flutterwave\Payments\Data\DTO\PaymentRequest;
use Flutterwave\Payments\Data\Currency;

it('can create customer DTO', function () {
    $customer = new Customer('test@example.com', 'John Doe', '+2348123456789');
    
    expect($customer->email)->toBe('test@example.com');
    expect($customer->name)->toBe('John Doe');
    expect($customer->phonenumber)->toBe('+2348123456789');
});

it('can create customer DTO with make method', function () {
    $customer = Customer::make('test@example.com', 'John Doe');
    
    expect($customer->email)->toBe('test@example.com');
    expect($customer->name)->toBe('John Doe');
    expect($customer->phonenumber)->toBeNull();
});

it('converts customer to array correctly', function () {
    $customer = Customer::make('test@example.com', 'John Doe', '+2348123456789');
    $array = $customer->toArray();
    
    expect($array)->toBe([
        'email' => 'test@example.com',
        'name' => 'John Doe',
        'phonenumber' => '+2348123456789',
    ]);
});

it('omits null values in customer array', function () {
    $customer = Customer::make('test@example.com');
    $array = $customer->toArray();
    
    expect($array)->toBe([
        'email' => 'test@example.com',
    ]);
});

it('can create payment request DTO', function () {
    $customer = Customer::make('test@example.com');
    $payment = new PaymentRequest('TX_REF_123', 1000.0, Currency::NGN, $customer);
    
    expect($payment->tx_ref)->toBe('TX_REF_123');
    expect($payment->amount)->toBe(1000.0);
    expect($payment->currency)->toBe(Currency::NGN);
    expect($payment->customer)->toBe($customer);
});

it('can create payment request with make method', function () {
    $customer = Customer::make('test@example.com');
    $payment = PaymentRequest::make('TX_REF_123', 1000.0, Currency::NGN, $customer, 'https://example.com');
    
    expect($payment->tx_ref)->toBe('TX_REF_123');
    expect($payment->redirect_url)->toBe('https://example.com');
});

it('can add meta to payment request', function () {
    $customer = Customer::make('test@example.com');
    $payment = PaymentRequest::make('TX_REF_123', 1000.0, Currency::NGN, $customer)
        ->withMeta(['order_id' => '12345']);
    
    expect($payment->meta)->toBe(['order_id' => '12345']);
});

it('can add customizations to payment request', function () {
    $customer = Customer::make('test@example.com');
    $payment = PaymentRequest::make('TX_REF_123', 1000.0, Currency::NGN, $customer)
        ->withCustomizations(['title' => 'My Store']);
    
    expect($payment->customizations)->toBe(['title' => 'My Store']);
});

it('converts payment request to array correctly', function () {
    $customer = Customer::make('test@example.com', 'John Doe');
    $payment = PaymentRequest::make('TX_REF_123', 1000.0, Currency::NGN, $customer, 'https://example.com')
        ->withMeta(['order_id' => '12345']);
    
    $array = $payment->toArray();
    
    expect($array)->toBe([
        'tx_ref' => 'TX_REF_123',
        'amount' => 1000.0,
        'currency' => Currency::NGN,
        'customer' => [
            'email' => 'test@example.com',
            'name' => 'John Doe',
        ],
        'redirect_url' => 'https://example.com',
        'meta' => ['order_id' => '12345'],
    ]);
});

it('omits null values in payment request array', function () {
    $customer = Customer::make('test@example.com');
    $payment = PaymentRequest::make('TX_REF_123', 1000.0, Currency::NGN, $customer);
    
    $array = $payment->toArray();
    
    expect($array)->toBe([
        'tx_ref' => 'TX_REF_123',
        'amount' => 1000.0,
        'currency' => Currency::NGN,
        'customer' => [
            'email' => 'test@example.com',
        ],
    ]);
});