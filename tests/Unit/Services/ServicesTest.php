<?php

use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Services\Transfers;

it('requires secret key for transfers service', function () {
    $api = new Api();
    $config = [];
    
    expect(fn () => new Transfers($api, $config))
        ->toThrow(InvalidArgument::class, 'The secret key is required. please add it to the .env file.');
});

it('can create transfers service with valid config', function () {
    $api = new Api();
    $config = ['secret_key' => 'test_secret_key'];
    
    $transfers = new Transfers($api, $config);
    
    expect($transfers)->toBeInstanceOf(Transfers::class);
});

it('validates phone number for mobile money', function () {
    $api = new Api();
    $config = ['secret_key' => 'test_secret_key'];
    
    $mobileMoney = new \Flutterwave\Payments\Services\MobileMoney($api, $config);
    
    // Valid Nigerian number
    expect($mobileMoney->validatePhoneNumber('+2348123456789', 'NG'))->toBeTrue();
    expect($mobileMoney->validatePhoneNumber('08123456789', 'NG'))->toBeTrue();
    
    // Invalid Nigerian number
    expect($mobileMoney->validatePhoneNumber('+234123456789', 'NG'))->toBeFalse();
    
    // Valid Ghanaian number
    expect($mobileMoney->validatePhoneNumber('+233241234567', 'GH'))->toBeTrue();
    expect($mobileMoney->validatePhoneNumber('0241234567', 'GH'))->toBeTrue();
});

it('formats phone numbers correctly', function () {
    $api = new Api();
    $config = ['secret_key' => 'test_secret_key'];
    
    $mobileMoney = new \Flutterwave\Payments\Services\MobileMoney($api, $config);
    
    // Nigerian numbers
    expect($mobileMoney->formatPhoneNumber('08123456789', 'NG'))->toBe('2348123456789');
    expect($mobileMoney->formatPhoneNumber('+2348123456789', 'NG'))->toBe('+2348123456789');
    
    // Ghanaian numbers
    expect($mobileMoney->formatPhoneNumber('0241234567', 'GH'))->toBe('233241234567');
    expect($mobileMoney->formatPhoneNumber('+233241234567', 'GH'))->toBe('+233241234567');
});

it('returns correct mobile networks for countries', function () {
    $api = new Api();
    $config = ['secret_key' => 'test_secret_key'];
    
    $mobileMoney = new \Flutterwave\Payments\Services\MobileMoney($api, $config);
    
    expect($mobileMoney->getNetworks('NG'))->toBe(['mtn', 'airtel', 'glo', '9mobile']);
    expect($mobileMoney->getNetworks('GH'))->toBe(['mtn', 'vodafone', 'airteltigo']);
    expect($mobileMoney->getNetworks('KE'))->toBe(['mpesa']);
    expect($mobileMoney->getNetworks('XX'))->toBe([]); // Unknown country
});