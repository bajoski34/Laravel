<?php

namespace Flutterwave\Payments\Tests;

use Flutterwave\Payments\Providers\PackageServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageTestCase extends TestCase
{
    protected $loadEnvironmentVariables = true;

    protected function testGetPackageProviders($app): array
    {
        return [
            PackageServiceProvider::class,
        ];
    }
}
