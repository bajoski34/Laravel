<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Providers;

use Flutterwave\Payments\Console\Commands\MakePaymentCommand;
use Flutterwave\Payments\Console\Commands\RefundTransactionCommand;
use Flutterwave\Payments\Console\Commands\VerifyWebhookCommand;
use Flutterwave\Payments\Flutterwave;

final class FlutterwaveServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flutterwave');

        // Publish config file
        $this->publishes([
            __DIR__.'/../config/flutterwave.php' => config_path('flutterwave.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../routes/web.php' => base_path('routes/vendor/flutterwave/web.php'),
        ], 'routes');

        // Check if the routes file exists, and then include it
        if (file_exists(base_path('routes/vendor/flutterwave/web.php'))) {
            require base_path('routes/vendor/flutterwave/web.php');
        }

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakePaymentCommand::class,
                VerifyWebhookCommand::class,
                RefundTransactionCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flutterwave.php', 'flutterwave');

        $this->app->singleton('flutterwave', function() {
            return new Flutterwave();
        } );

        $this->app->alias('flutterwave', "Flutterwave\Payments\Flutterwave");
    }
}
