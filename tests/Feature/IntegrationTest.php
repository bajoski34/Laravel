<?php

use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
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
        ->toContain('class="flutterwave-pay-button btn"')
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

it('requires a public key for inline checkout', function () {
    config(['flutterwave.publicKey' => null]);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();

    app('flutterwave')->render('inline', ['amount' => 10, 'email' => 'a@b.co']);
})->throws(InvalidArgument::class, 'FLW_PUBLIC_KEY');
