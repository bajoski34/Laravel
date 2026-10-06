<?php

namespace Flutterwave\Payments\Tests;

use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Providers\FlutterwaveServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FlutterwaveServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Flutterwave' => Flutterwave::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('flutterwave.publicKey', 'FLWPUBK_TEST-public');
        $app['config']->set('flutterwave.secretKey', 'FLWSECK_TEST-secret');
        $app['config']->set('flutterwave.secretHash', 'my-secret-hash');
        $app['config']->set('flutterwave.redirectUrl', 'https://shop.test/flutterwave/payment/callback');
        $app['config']->set('flutterwave.retries', 1);
    }
}
