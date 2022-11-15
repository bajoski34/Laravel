<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Providers;

use Flutterwave\Payments\Flutterwave;

final class FlutterwaveServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flutterwave.php', 'flutterwave');

        $this->app->singleton('flutterwave', function() {
            return new Flutterwave();
        } );

        $this->app->alias('flutterwave', "Flutterwave\Payments\Flutterwave");
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flutterwave');

        // Publish config file
        $this->publishes([
            __DIR__.'/../config/flutterwave.php' => config_path('flutterwave.php'),
        ], 'config');

//        // Publish Views
//        $this->publishes([
//            __DIR__ . '/../resources/views' => resource_path('views/vendor/Flutterwave')
//        ], 'flutterwave-views');
    }
}
