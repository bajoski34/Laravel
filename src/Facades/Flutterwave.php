<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Facades;

use \Illuminate\Support\Facades\Facade;

class Flutterwave extends Facade
{
    /**
     * Get the registered name of the component
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'flutterwave';
    }
}
