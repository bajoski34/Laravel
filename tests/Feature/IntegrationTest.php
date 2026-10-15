<?php

use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

it('registers the flutterwave log channel automatically', function () {
    expect(config('logging.channels.flutterwave.driver'))->toBe('single');
    Log::channel('flutterwave')->info('works');
});

it('renders a pay button', function () {
    $html = Blade::render('<x-flutterwave-button amount="2500" email="jane@example.com" currency="USD" class="btn" :meta="[\'order_id\' => 7]">Buy now</x-flutterwave-button>');

    expect($html)
        ->toContain('https://checkout.flutterwave.com/v3.js')
        ->toContain('class="flw flw-btn flw-btn--primary flutterwave-pay-button btn"')
        ->toContain('/flutterwave/assets/flutterwave.css?v=')
        ->toContain('Buy now')
        ->toContain('FlutterwaveCheckout(config)');

    preg_match('/var config = (\{.*\});/', $html, $matches);
    expect(json_decode($matches[1], true))->toMatchArray([
        'public_key' => 'FLWPUBK_TEST-public',
        'amount' => '2500',
        'currency' => 'USD',
        'customer' => ['email' => 'jane@example.com'],
        'meta' => ['order_id' => 7],
    ]);
});

it('uses the default label when the button has no content', function () {
    expect(Blade::render('<x-flutterwave-button amount="10" email="a@b.co" />'))->toContain('Pay Now');
});

it('verifies a transaction from the command line', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        'id' => 1, 'tx_ref' => 'ORDER-1', 'status' => 'successful', 'amount' => 100, 'currency' => 'NGN',
    ]])]);

    $this->artisan('flutterwave:verify', ['reference' => '1'])
        ->expectsOutputToContain('successful')
        ->assertSuccessful();
});

it('fails the verify command when the transaction is missing', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'No transaction was found'], 400)]);

    $this->artisan('flutterwave:verify', ['reference' => 'ORDER-X', '--ref' => true])
        ->expectsOutputToContain('No transaction was found')
        ->assertFailed();
});

it('lists banks from the command line', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        ['code' => '044', 'name' => 'Access Bank'],
        ['code' => '058', 'name' => 'GTBank Plc'],
    ]])]);

    $this->artisan('flutterwave:banks', ['--search' => 'access'])
        ->expectsOutputToContain('Access Bank')
        ->doesntExpectOutputToContain('GTBank')
        ->assertSuccessful();
});

it('installs the config and env keys', function () {
    $env = sys_get_temp_dir().'/flw-'.uniqid();
    mkdir($env);
    file_put_contents($env.'/.env', "APP_NAME=Shop\nFLW_PUBLIC_KEY=existing\n");
    app()->useEnvironmentPath($env);

    $this->artisan('flutterwave:install')
        ->expectsConfirmation('Add FLW_SECRET_KEY, FLW_SECRET_HASH, FLW_ENCRYPTION_KEY to your .env file?', 'yes')
        ->expectsOutputToContain(url('flutterwave/webhook'))
        ->expectsConfirmation('Would you like to star the repo on GitHub? It helps other developers find it.', 'no')
        ->assertSuccessful();

    $contents = file_get_contents($env.'/.env');
    expect($contents)->toContain('FLW_PUBLIC_KEY=existing')
        ->toContain("FLW_SECRET_KEY=\n")
        ->toContain('FLW_SECRET_HASH=')
        ->and(substr_count($contents, 'FLW_PUBLIC_KEY='))->toBe(1)
        ->and(file_exists(config_path('flutterwave.php')))->toBeTrue();

    @unlink(config_path('flutterwave.php'));
});

it('publishes the inertia components for each framework', function (string $framework, array $files) {
    $target = resource_path('js/components/flutterwave');
    app()->useEnvironmentPath(sys_get_temp_dir().'/flw-missing-'.uniqid());

    $this->artisan('flutterwave:install', ["--{$framework}" => true, '--no-interaction' => true])
        ->expectsOutputToContain("Published the {$framework} components to resources/js/components/flutterwave")
        ->expectsOutputToContain("from '@/components/flutterwave'")
        ->assertSuccessful();

    $published = array_map('basename', glob($target.'/*'));
    sort($published);

    // Clean up before asserting, so a failure here can't leave files behind for other tests.
    app('files')->deleteDirectory($target);
    @unlink(config_path('flutterwave.php'));

    expect($published)->toBe($files);
})->with([
    'react' => ['react', ['admin.ts', 'bank-select.tsx', 'beneficiaries.tsx', 'chargebacks.tsx', 'checkout.ts', 'flutterwave-css.d.ts', 'flutterwave-pay-button.tsx', 'flutterwave.css', 'fonts', 'format.ts', 'fx-converter.tsx', 'icons.tsx', 'index.ts', 'pager.tsx', 'refunds.tsx', 'settlements.tsx', 'transactions.tsx', 'types.ts', 'use-flutterwave-checkout.ts', 'virtual-account.tsx']],
    'vue' => ['vue', ['BankSelect.vue', 'Beneficiaries.vue', 'ChargebackItem.vue', 'Chargebacks.vue', 'FlutterwavePayButton.vue', 'FxConverter.vue', 'Pager.vue', 'Refunds.vue', 'Settlements.vue', 'TransactionItem.vue', 'Transactions.vue', 'VirtualAccount.vue', 'admin.ts', 'checkout.ts', 'flutterwave-css.d.ts', 'flutterwave.css', 'fonts', 'format.ts', 'index.ts', 'types.ts', 'useFlutterwaveCheckout.ts']],
    'svelte' => ['svelte', ['BankSelect.svelte', 'Beneficiaries.svelte', 'ChargebackItem.svelte', 'Chargebacks.svelte', 'FlutterwavePayButton.svelte', 'FxConverter.svelte', 'Pager.svelte', 'Refunds.svelte', 'Settlements.svelte', 'TransactionItem.svelte', 'Transactions.svelte', 'VirtualAccount.svelte', 'admin.ts', 'checkout.ts', 'flutterwave-css.d.ts', 'flutterwave.css', 'fonts', 'format.ts', 'index.ts', 'types.ts']],
]);

it('refuses to publish more than one framework before changing anything', function () {
    @unlink(config_path('flutterwave.php'));

    $this->artisan('flutterwave:install', ['--react' => true, '--vue' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Pick one of --react, --vue or --svelte')
        ->assertFailed();

    expect(file_exists(config_path('flutterwave.php')))->toBeFalse()
        ->and(is_dir(resource_path('js/components/flutterwave')))->toBeFalse();
});

it('keeps -- out of command argument descriptions', function () {
    // Laravel 9 parses any signature token containing -- as an option, so an argument
    // described as "... with --trade" registers that option twice and breaks artisan.
    $commands = collect(Artisan::all())->filter(fn ($command, string $name) => str_starts_with($name, 'flutterwave:'));

    expect($commands)->not->toBeEmpty();

    $commands->each(function ($command, string $name) {
        $signature = (string) (new ReflectionProperty($command, 'signature'))->getValue($command);
        preg_match_all('/\{\s*([^-\s][^}]*)\}/', $signature, $arguments);

        foreach ($arguments[1] as $argument) {
            expect(str_contains($argument, '--'))->toBeFalse("{$name}: the argument description \"{$argument}\" mentions an option with --");
        }
    });
});

it('requires a public key for inline checkout', function () {
    config(['flutterwave.publicKey' => null]);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    app('flutterwave')->render('inline', ['amount' => 10, 'email' => 'a@b.co']);
})->throws(InvalidArgument::class, 'FLW_PUBLIC_KEY');
