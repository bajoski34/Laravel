<?php

use Flutterwave\Payments\Helpers\Utils;
use Flutterwave\Payments\Data\Currency;

it('validates email addresses correctly', function () {
    expect(Utils::validateEmail('test@example.com'))->toBeTrue();
    expect(Utils::validateEmail('invalid-email'))->toBeFalse();
    expect(Utils::validateEmail(''))->toBeFalse();
});

it('validates amounts correctly', function () {
    expect(Utils::validateAmount(100.0))->toBeTrue();
    expect(Utils::validateAmount(0.01))->toBeTrue();
    expect(Utils::validateAmount(0.0))->toBeFalse();
    expect(Utils::validateAmount(-10.0))->toBeFalse();
});

it('formats amounts correctly', function () {
    expect(Utils::formatAmount(100.123))->toBe(100.12);
    expect(Utils::formatAmount(100.0))->toBe(100.0);
    expect(Utils::formatAmount(100.999))->toBe(101.0);
});

it('generates transaction references', function () {
    $ref1 = Utils::generateReference();
    $ref2 = Utils::generateReference('TEST');
    
    expect($ref1)->toMatch('/^FLW_[a-zA-Z0-9]{10}_\d+$/');
    expect($ref2)->toMatch('/^TEST_[a-zA-Z0-9]{10}_\d+$/');
    expect($ref1)->not->toBe($ref2);
});

it('converts amounts to subunits', function () {
    expect(Utils::convertToSubunit(100.0, Currency::NGN))->toBe(10000);
    expect(Utils::convertToSubunit(1.23, Currency::USD))->toBe(123);
    expect(Utils::convertToSubunit(100.0, 'XYZ'))->toBe(100); // Unknown currency
});

it('converts amounts from subunits', function () {
    expect(Utils::convertFromSubunit(10000, Currency::NGN))->toBe(100.0);
    expect(Utils::convertFromSubunit(123, Currency::USD))->toBe(1.23);
    expect(Utils::convertFromSubunit(100, 'XYZ'))->toBe(100.0); // Unknown currency
});

it('gets currency symbols', function () {
    expect(Utils::getCurrencySymbol(Currency::NGN))->toBe('₦');
    expect(Utils::getCurrencySymbol(Currency::USD))->toBe('$');
    expect(Utils::getCurrencySymbol(Currency::GBP))->toBe('£');
    expect(Utils::getCurrencySymbol('XYZ'))->toBe('XYZ'); // Unknown currency
});

it('formats currency amounts', function () {
    expect(Utils::formatCurrency(1000.0, Currency::NGN))->toBe('₦1,000.00');
    expect(Utils::formatCurrency(123.45, Currency::USD))->toBe('$123.45');
});

it('validates transaction references', function () {
    expect(Utils::validateTransactionReference('valid_ref_123'))->toBeTrue();
    expect(Utils::validateTransactionReference('VALID-REF-123'))->toBeTrue();
    expect(Utils::validateTransactionReference('ab'))->toBeFalse(); // Too short
    expect(Utils::validateTransactionReference('invalid ref!'))->toBeFalse(); // Invalid characters
});

it('masks sensitive data', function () {
    $data = [
        'card_number' => '1234567890123456',
        'cvv' => '123',
        'email' => 'test@example.com',
        'amount' => 1000,
    ];
    
    $masked = Utils::maskSensitiveData($data);
    
    expect($masked['card_number'])->toBe('12**********56');
    expect($masked['cvv'])->toBe('***');
    expect($masked['email'])->toBe('test@example.com'); // Not sensitive
    expect($masked['amount'])->toBe(1000); // Not sensitive
});

it('returns supported countries', function () {
    $countries = Utils::getSupportedCountries();
    
    expect($countries)->toBeArray();
    expect($countries['NG'])->toBe(['name' => 'Nigeria', 'currency' => Currency::NGN]);
    expect($countries['US'])->toBe(['name' => 'United States', 'currency' => Currency::USD]);
});

it('gets country by currency', function () {
    expect(Utils::getCountryByCurrency(Currency::NGN))->toBe('NG');
    expect(Utils::getCountryByCurrency(Currency::USD))->toBe('US');
    expect(Utils::getCountryByCurrency('XYZ'))->toBeNull();
});

it('checks if currency is supported', function () {
    expect(Utils::isCurrencySupported(Currency::NGN))->toBeTrue();
    expect(Utils::isCurrencySupported(Currency::USD))->toBeTrue();
    expect(Utils::isCurrencySupported('XYZ'))->toBeFalse();
});