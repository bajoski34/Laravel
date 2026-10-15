<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Providers;

use Flutterwave\Payments\Console\AcceptChargebackCommand;
use Flutterwave\Payments\Console\BalanceCommand;
use Flutterwave\Payments\Console\CardBinCommand;
use Flutterwave\Payments\Console\DeclineChargebackCommand;
use Flutterwave\Payments\Console\DoctorCommand;
use Flutterwave\Payments\Console\ExchangeRateCommand;
use Flutterwave\Payments\Console\FxQuoteCommand;
use Flutterwave\Payments\Console\FxStatusCommand;
use Flutterwave\Payments\Console\FxTradeCommand;
use Flutterwave\Payments\Console\InstallCommand;
use Flutterwave\Payments\Console\ListBanksCommand;
use Flutterwave\Payments\Console\ListBillsCommand;
use Flutterwave\Payments\Console\ListBranchesCommand;
use Flutterwave\Payments\Console\ListChargebacksCommand;
use Flutterwave\Payments\Console\PaymentLinkCommand;
use Flutterwave\Payments\Console\PingWebhookCommand;
use Flutterwave\Payments\Console\ResendWebhookCommand;
use Flutterwave\Payments\Console\ResolveAccountCommand;
use Flutterwave\Payments\Console\VerifyTransactionCommand;
use Flutterwave\Payments\Events\ChargebackUpdated;
use Flutterwave\Payments\Events\TransferCompleted;
use Flutterwave\Payments\Flutterwave;
use Flutterwave\Payments\Http\Controllers\AdminController;
use Flutterwave\Payments\Http\Controllers\ChargebackController;
use Flutterwave\Payments\Http\Controllers\FxController;
use Flutterwave\Payments\Http\Controllers\WebhookController;
use Flutterwave\Payments\Http\Middleware\VerifyWebhookSignature;
use Flutterwave\Payments\Notifications\ChargebackRaised;
use Flutterwave\Payments\Notifications\TransferFailed;
use Flutterwave\Payments\View\Components\Chargebacks;
use Flutterwave\Payments\View\Components\FxConverter;
use Flutterwave\Payments\View\Components\PayButton;
use Flutterwave\Payments\View\Components\VirtualAccount;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class FlutterwaveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flutterwave');
        Blade::component('flutterwave-button', PayButton::class);
        Blade::component('flutterwave-virtual-account', VirtualAccount::class);
        Blade::component('flutterwave-chargebacks', Chargebacks::class);
        Blade::component('flutterwave-fx', FxConverter::class);

        $this->publishes([
            __DIR__.'/../config/flutterwave.php' => config_path('flutterwave.php'),
        ], ['flutterwave-config', 'config']);

        $this->publishes([
            __DIR__.'/../routes/web.php' => base_path('routes/vendor/flutterwave/web.php'),
        ], ['flutterwave-routes', 'routes']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/flutterwave'),
        ], 'flutterwave-views');

        $this->publishes([
            __DIR__.'/../database/migrations/add_flutterwave_card_columns.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_add_flutterwave_card_columns.php'),
        ], 'flutterwave-migrations');

        // Each framework's components are published alongside the shared checkout helpers and types.
        foreach (['react', 'vue', 'svelte'] as $framework) {
            $this->publishes([
                __DIR__.'/../resources/js/shared' => resource_path('js/components/flutterwave'),
                __DIR__."/../resources/js/{$framework}" => resource_path('js/components/flutterwave'),
            ], "flutterwave-{$framework}");
        }

        $this->registerRoutes();
        $this->registerAdminRouteMacro();
        $this->registerNotifications();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                VerifyTransactionCommand::class,
                ListBanksCommand::class,
                ListBranchesCommand::class,
                ResolveAccountCommand::class,
                BalanceCommand::class,
                CardBinCommand::class,
                ExchangeRateCommand::class,
                PaymentLinkCommand::class,
                ResendWebhookCommand::class,
                PingWebhookCommand::class,
                ListChargebacksCommand::class,
                AcceptChargebackCommand::class,
                DeclineChargebackCommand::class,
                FxQuoteCommand::class,
                FxTradeCommand::class,
                FxStatusCommand::class,
                ListBillsCommand::class,
                DoctorCommand::class,
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

    /**
     * Route::flutterwaveAdmin() registers the admin page (unless $page is false) and the
     * endpoints behind the chargeback and FX components. They refund customers and move
     * money, so call it inside your own authenticated route group. Every action and the
     * page also check the `flutterwave.manage` ability.
     */
    /**
     * Send ChargebackRaised and TransferFailed to the addresses in flutterwave.notify, when set.
     */
    private function registerNotifications(): void
    {
        $recipients = static fn (?string $list) => array_values(array_filter(array_map('trim', explode(',', (string) $list))));

        Event::listen(ChargebackUpdated::class, function (ChargebackUpdated $event) use ($recipients) {
            $to = $recipients(config('flutterwave.notify.chargebacks'));

            if ($to !== [] && $event->needsResponse()) {
                Notification::route('mail', $to)->notify(new ChargebackRaised($event->data));
            }
        });

        Event::listen(TransferCompleted::class, function (TransferCompleted $event) use ($recipients) {
            $to = $recipients(config('flutterwave.notify.failed_transfers'));

            if ($to !== [] && strtoupper((string) ($event->data['status'] ?? '')) === 'FAILED') {
                Notification::route('mail', $to)->notify(new TransferFailed($event->data));
            }
        });
    }

    private function registerAdminRouteMacro(): void
    {
        Route::macro('flutterwaveAdmin', function (string $prefix = 'flutterwave', bool $page = true) {
            Route::prefix($prefix)->name('flutterwave.')->group(function () use ($page) {
                if ($page) {
                    Route::get('/', AdminController::class)->name('admin');
                }

                Route::post('chargebacks/{chargeback}/accept', [ChargebackController::class, 'accept'])->name('chargebacks.accept');
                Route::post('chargebacks/{chargeback}/decline', [ChargebackController::class, 'decline'])->name('chargebacks.decline');
                Route::post('fx/quotes', [FxController::class, 'quote'])->name('fx.quote');
                Route::post('fx/trades', [FxController::class, 'trade'])->name('fx.trade');
            });
        });
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
