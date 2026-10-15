<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'flutterwave:doctor
        {--offline : Skip the check that calls the Flutterwave API}';

    protected $description = 'Check your Flutterwave setup: keys, webhooks, cache, queue and API access';

    private const OK = 'ok';

    private const WARN = 'warn';

    private const FAIL = 'fail';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkKeys();
        $this->checkWebhooks();
        $this->checkCheckout();
        $this->checkCache();
        $this->checkQueue();
        $this->checkAdmin();

        if (! $this->option('offline')) {
            $this->checkApi();
        }

        $symbols = [self::OK => '<info>✓</info>', self::WARN => '<comment>!</comment>', self::FAIL => '<error>✗</error>'];
        $this->table(['', 'Check', 'Result'], array_map(static fn (array $result) => [$symbols[$result[0]], $result[1], $result[2]], $this->results));

        $failed = count(array_filter($this->results, static fn (array $result) => $result[0] === self::FAIL));
        $warned = count(array_filter($this->results, static fn (array $result) => $result[0] === self::WARN));

        if ($failed > 0) {
            $this->error("{$failed} ".($failed === 1 ? 'problem needs' : 'problems need').' fixing before Flutterwave will work.');

            return self::FAILURE;
        }

        $this->info($warned > 0 ? "Flutterwave is set up. Review the {$warned} ".($warned === 1 ? 'warning' : 'warnings').' above.' : 'Flutterwave is set up correctly.');

        return self::SUCCESS;
    }

    private function checkKeys(): void
    {
        $secret = (string) config('flutterwave.secretKey');
        $public = (string) config('flutterwave.publicKey');

        if ($secret === '') {
            $this->result(self::FAIL, 'Secret key', 'FLW_SECRET_KEY is empty. Copy it from Settings > API keys on your Flutterwave dashboard.');

            return;
        }

        $this->result(self::OK, 'Secret key', 'Set ('.(str_contains($secret, '_TEST') ? 'test' : 'live').' mode)');

        if (str_contains($secret, '_TEST')) {
            $this->result(self::OK, 'Sandbox transfers', "Whitelist this server's IP on your dashboard first. Test transfers stay pending unless you pass test_outcome.");
        }

        if ($public === '') {
            $this->result(self::WARN, 'Public key', 'FLW_PUBLIC_KEY is empty. The inline checkout and pay buttons need it.');
        } elseif (str_contains($public, '_TEST') !== str_contains($secret, '_TEST')) {
            $this->result(self::FAIL, 'Public key', 'One key is a test key and the other is live. Use both from the same mode.');
        } else {
            $this->result(self::OK, 'Public key', 'Set, same mode as the secret key');
        }
    }

    private function checkWebhooks(): void
    {
        if (! config('flutterwave.webhook.enabled', true)) {
            $this->result(self::WARN, 'Webhooks', 'Turned off, so payment events never fire. Set FLW_WEBHOOK_ENABLED=true to receive them.');

            return;
        }

        if ((string) config('flutterwave.secretHash') === '') {
            $this->result(self::FAIL, 'Webhook secret hash', 'FLW_SECRET_HASH is empty, so every webhook is rejected. Set it to the secret hash on your dashboard (Settings > Webhooks).');
        } else {
            $this->result(self::OK, 'Webhook secret hash', 'Set');
        }

        $url = url((string) config('flutterwave.webhook.path', 'flutterwave/webhook'));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $local = in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true) || preg_match('/\.(test|local|localhost)$/', $host);

        if ($local) {
            $this->result(self::WARN, 'Webhook URL', "{$url} can't be reached by Flutterwave. Use flutterwave:ping-webhook to test locally, and set APP_URL in production.");
        } elseif (str_starts_with($url, 'http://')) {
            $this->result(self::WARN, 'Webhook URL', "{$url} isn't HTTPS. Use HTTPS so webhooks can't be read in transit.");
        } else {
            $this->result(self::OK, 'Webhook URL', "{$url} (set this on your dashboard)");
        }
    }

    private function checkCheckout(): void
    {
        $host = (string) parse_url(url('/'), PHP_URL_HOST);

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->result(self::WARN, 'App address', "Flutterwave's checkout doesn't load on an IP address like {$host}. Open your app on localhost or a domain, and set APP_URL to match.");
        }

        if ((string) config('flutterwave.redirectUrl') === '') {
            $this->result(self::WARN, 'Redirect URL', 'FLW_REDIRECT_URL is empty, so customers stay on Flutterwave after paying, and saved card charges need a redirect_url each time.');
        } else {
            $this->result(self::OK, 'Redirect URL', (string) config('flutterwave.redirectUrl'));
        }
    }

    private function checkCache(): void
    {
        if (! config('flutterwave.webhook.dedupe', true)) {
            $this->result(self::WARN, 'Duplicate webhooks', 'Turned off, so a retried webhook fires its events again.');

            return;
        }

        $name = config('flutterwave.webhook.dedupe_store') ?: config('cache.default');

        try {
            $store = Cache::store(config('flutterwave.webhook.dedupe_store'));
            $key = 'flutterwave:doctor:'.bin2hex(random_bytes(4));
            $store->put($key, 'ok', 10);
            $works = $store->get($key) === 'ok';
            $store->forget($key);
        } catch (Throwable $e) {
            $this->result(self::FAIL, 'Cache', "The {$name} cache store doesn't work: {$e->getMessage()}");

            return;
        }

        $driver = (string) config("cache.stores.{$name}.driver", $name);

        if (! $works) {
            $this->result(self::FAIL, 'Cache', "The {$name} cache store didn't keep a value, so handled webhooks can't be remembered.");
        } elseif (in_array($driver, ['array', 'null'], true)) {
            $this->result(self::WARN, 'Cache', "The {$name} store ({$driver}) forgets everything between requests, so retried webhooks fire again. Use redis or database (FLW_WEBHOOK_DEDUPE_STORE).");
        } elseif (! $store->getStore() instanceof LockProvider) {
            $this->result(self::WARN, 'Cache', "The {$name} store can't lock, so two copies of a webhook arriving together could both run.");
        } else {
            $this->result(self::OK, 'Cache', "{$name} ({$driver}) remembers handled webhooks");
        }
    }

    private function checkQueue(): void
    {
        $queue = config('flutterwave.webhook.queue');

        if (! $queue) {
            $this->result(self::OK, 'Webhook queue', 'Handled during the request (set FLW_WEBHOOK_QUEUE to queue them)');

            return;
        }

        $connection = config('flutterwave.webhook.queue_connection') ?: config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($driver === null) {
            $this->result(self::FAIL, 'Webhook queue', "The queue connection {$connection} isn't configured in config/queue.php.");
        } elseif ($driver === 'sync') {
            $this->result(self::WARN, 'Webhook queue', "The {$connection} connection is sync, so webhooks still run during the request.");
        } else {
            $this->result(self::OK, 'Webhook queue', "{$queue} on {$connection} ({$driver}). Keep a worker running.");
        }
    }

    private function checkAdmin(): void
    {
        if (! Route::has('flutterwave.chargebacks.accept')) {
            return;
        }

        if (! Gate::has('flutterwave.manage')) {
            $this->result(self::WARN, 'Admin screens', "Routes are registered but the flutterwave.manage ability isn't defined, so every action returns 403. Define it with Gate::define().");
        } else {
            $this->result(self::OK, 'Admin screens', 'Routes registered and flutterwave.manage defined');
        }
    }

    private function checkApi(): void
    {
        if ((string) config('flutterwave.secretKey') === '') {
            return;
        }

        try {
            $balances = $this->laravel->make('flutterwave')->balances()->all()['data'] ?? [];
            $this->result(self::OK, 'API access', 'Flutterwave accepted the secret key ('.count($balances).' wallet '.(count($balances) === 1 ? 'balance' : 'balances').')');
        } catch (FlutterwaveException $e) {
            $this->result(self::FAIL, 'API access', 'Flutterwave rejected the request: '.$e->getMessage());
        }
    }

    private function result(string $status, string $check, string $detail): void
    {
        $this->results[] = [$status, $check, $detail];
    }
}
