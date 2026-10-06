<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Providers;

use Flutterwave\Payments\Console\InstallCommand;
use Flutterwave\Payments\Console\ListBanksCommand;
use Flutterwave\Payments\Console\VerifyTransactionCommand;
use Flutterwave\Payments\Flutterwave;
use Flutterwave\Payments\Http\Controllers\WebhookController;
use Flutterwave\Payments\Http\Middleware\VerifyWebhookSignature;
use Flutterwave\Payments\View\Components\PayButton;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FlutterwaveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flutterwave');
        Blade::component('flutterwave-button', PayButton::class);

        $this->publishes([
            __DIR__.'/../config/flutterwave.php' => config_path('flutterwave.php'),
        ], ['flutterwave-config', 'config']);

        $this->publishes([
            __DIR__.'/../routes/web.php' => base_path('routes/vendor/flutterwave/web.php'),
        ], ['flutterwave-routes', 'routes']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/flutterwave'),
        ], 'flutterwave-views');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                VerifyTransactionCommand::class,
                ListBanksCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flutterwave.php', 'flutterwave');

        $config = $this->app['config'];
        if (! $config->has('logging.channels.flutterwave')) {
            $logging = require __DIR__.'/../config/logging.php';
            $config->set('logging.channels.flutterwave', $logging['channels']['flutterwave']);
        }

        $this->app->singleton('flutterwave', function () {
            return new Flutterwave;
        });

        $this->app->alias('flutterwave', Flutterwave::class);
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        if (config('flutterwave.webhook.enabled', true)) {
            Route::post(config('flutterwave.webhook.path', 'flutterwave/webhook'), WebhookController::class)
                ->middleware(array_merge((array) config('flutterwave.webhook.middleware', []), [VerifyWebhookSignature::class]))
                ->name('flutterwave.webhooks.handle');
        }

        // Example checkout/callback routes, published with --tag=flutterwave-routes.
        if (file_exists(base_path('routes/vendor/flutterwave/web.php'))) {
            require base_path('routes/vendor/flutterwave/web.php');
        }
    }
}
