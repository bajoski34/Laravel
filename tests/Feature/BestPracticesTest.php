<?php

use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Support\Checksum;
use Flutterwave\Payments\Testing\SandboxData;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

function freshFlutterwave(array $config = []): void
{
    config($config);
    app()->forgetInstance('flutterwave');
    Flutterwave::clearResolvedInstances();
}

// ---------------------------------------------------------------- checksum

it('builds the same payload_hash as the example in the flutterwave docs', function () {
    // Output of Flutterwave's Node.js example with these inputs.
    $expected = '46650a3d690b6cef7b8bfb60c4ecbde9b6f176ba17eb16e3be081bab4d51c068';
    $payload = ['amount' => 100, 'currency' => 'NGN', 'customer' => ['email' => 'user@example.com'], 'tx_ref' => 'YOUR_PAYMENT_REFERENCE'];

    expect(Checksum::make($payload, 'FLWSECK_TEST-SANDBOXDEMOKEY-X'))->toBe($expected)
        // 100.0 is the number 100 once Flutterwave parses the JSON, so it hashes the same.
        ->and(Checksum::make([...$payload, 'amount' => 100.0], 'FLWSECK_TEST-SANDBOXDEMOKEY-X'))->toBe($expected)
        // A string amount is hashed exactly as sent.
        ->and(Checksum::make([...$payload, 'amount' => '100.00'], 'FLWSECK_TEST-SANDBOXDEMOKEY-X'))->not->toBe($expected)
        ->and(Checksum::make(['amount' => 100, 'currency' => 'NGN', 'email' => 'user@example.com', 'tx_ref' => 'YOUR_PAYMENT_REFERENCE'], 'FLWSECK_TEST-SANDBOXDEMOKEY-X'))->toBe($expected);
});

it('needs every hashed field', function () {
    expect(fn () => Checksum::make(['amount' => 100, 'currency' => 'NGN', 'tx_ref' => 'X'], 'key'))->toThrow(InvalidArgument::class, 'customer email')
        ->and(fn () => Checksum::make(['amount' => 100, 'currency' => 'NGN', 'email' => 'a@b.co', 'tx_ref' => 'X'], ''))->toThrow(InvalidArgument::class, 'secret key is missing');
});

it('leaves the checksum out by default', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test/pay']])]);

    expect(Flutterwave::inlineConfig(['amount' => 100, 'email' => 'a@b.co']))->not->toHaveKey('payload_hash');
    Flutterwave::checkout(['amount' => 100, 'email' => 'a@b.co']);

    Http::assertSent(fn (Request $request) => ! isset($request['payload_hash']));
});

it('adds the checksum to inline and hosted checkouts when turned on', function () {
    freshFlutterwave(['flutterwave.checksum' => true]);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.test/pay']])]);
    $expected = hash('sha256', '5000NGNjane@example.comORDER-42'.hash('sha256', 'FLWSECK_TEST-secret'));

    expect(Flutterwave::inlineConfig(['amount' => 5000, 'email' => 'jane@example.com', 'tx_ref' => 'ORDER-42'])['payload_hash'])->toBe($expected);
    Flutterwave::checkout(['amount' => 5000, 'email' => 'jane@example.com', 'tx_ref' => 'ORDER-42']);

    Http::assertSent(fn (Request $request) => $request['payload_hash'] === $expected);
});

it('puts the checksum on the blade pay button when turned on', function () {
    freshFlutterwave(['flutterwave.checksum' => true]);

    $html = Blade::render('<x-flutterwave-button amount="2500" email="jane@example.com" tx-ref="ORDER-7" />');

    expect($html)->toContain('"payload_hash":"'.hash('sha256', '2500NGNjane@example.comORDER-7'.hash('sha256', 'FLWSECK_TEST-secret')).'"');
});

// ---------------------------------------------------------------- verification

it('checks the reference as well as the amount and currency', function () {
    Flutterwave::fake()->transaction(4975363, ['amount' => 5000, 'currency' => 'NGN', 'tx_ref' => 'ORDER-42']);

    expect(Flutterwave::isSuccessful(4975363, 5000, 'NGN', 'ORDER-42'))->toBeTrue()
        ->and(Flutterwave::isSuccessful(4975363, 5000, 'NGN', 'ORDER-43'))->toBeFalse();
});

it('matches a completed charge against the order', function () {
    $event = new ChargeCompleted([], ['status' => 'successful', 'amount' => 5000, 'currency' => 'NGN', 'tx_ref' => 'ORDER-42'], true);

    expect($event->matches(5000, 'ngn', 'ORDER-42'))->toBeTrue()
        ->and($event->matches(5000, 'NGN'))->toBeTrue()
        ->and($event->matches(6000, 'NGN', 'ORDER-42'))->toBeFalse()
        ->and($event->matches(5000, 'USD', 'ORDER-42'))->toBeFalse()
        ->and($event->matches(5000, 'NGN', 'ORDER-41'))->toBeFalse()
        ->and((new ChargeCompleted([], ['status' => 'failed', 'amount' => 5000, 'currency' => 'NGN'], true))->matches(5000, 'NGN'))->toBeFalse();
});

// ---------------------------------------------------------------- sandbox transfers

it('tells sandbox transfers how to end', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []])]);
    $transfer = ['account_bank' => SandboxData::BANK_CODE, 'account_number' => SandboxData::BANK_ACCOUNTS[0], 'amount' => 500, 'currency' => 'NGN'];

    Flutterwave::transfers()->create([...$transfer, 'reference' => 'PAYOUT-1', 'test_outcome' => 'success']);
    Flutterwave::transfers()->create([...$transfer, 'reference' => 'PAYOUT-2', 'test_outcome' => 'failure', 'test_resolve_after' => 1]);
    Flutterwave::transfers()->bulk([[...$transfer, 'reference' => 'PAYOUT-3', 'test_outcome' => 'success', 'test_resolve_after' => 2], [...$transfer, 'reference' => 'PAYOUT-4']]);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers' && $request['reference'] === 'PAYOUT-1_PMCK' && ! isset($request['test_outcome']));
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transfers' && $request['reference'] === 'PAYOUT-2_PMCK_ST_FDU_1');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'bulk-transfers'
        && $request['bulk_data'][0]['reference'] === 'PAYOUT-3_PMCKDU_2'
        && $request['bulk_data'][1]['reference'] === 'PAYOUT-4'
        && ! isset($request['bulk_data'][0]['test_outcome']));
});

it('ignores sandbox outcomes with live keys', function () {
    freshFlutterwave(['flutterwave.secretKey' => 'FLWSECK-live-secret-X', 'flutterwave.publicKey' => 'FLWPUBK-live-public-X']);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []])]);

    Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000031', 'amount' => 500, 'currency' => 'NGN', 'reference' => 'PAYOUT-1', 'test_outcome' => 'success']);

    Http::assertSent(fn (Request $request) => $request['reference'] === 'PAYOUT-1' && ! isset($request['test_outcome']));
});

it('rejects an unknown sandbox outcome', function () {
    Http::fake();

    expect(fn () => Flutterwave::transfers()->create(['account_bank' => '044', 'account_number' => '0690000031', 'amount' => 500, 'currency' => 'NGN', 'test_outcome' => 'pending']))->toThrow(InvalidArgument::class, "test_outcome must be 'success' or 'failure'");
    Http::assertNothingSent();
});

it('fakes transfers to the sandbox blacklisted account', function () {
    Flutterwave::fake();

    expect(fn () => Flutterwave::transfers()->create(['account_bank' => SandboxData::BANK_CODE, 'account_number' => SandboxData::BLACKLISTED_ACCOUNT, 'amount' => 500, 'currency' => 'NGN']))
        ->toThrow(FlutterwaveException::class, 'Payout was made to a blacklisted account number');
});

// ---------------------------------------------------------------- audit log and admin page

function auditLog(): string
{
    $path = sys_get_temp_dir().'/flw-audit-'.getmypid().'.log';
    config(['logging.channels.flutterwave' => ['driver' => 'single', 'path' => $path, 'level' => 'debug']]);
    app('log')->forgetChannel('flutterwave');
    @unlink($path);

    return $path;
}

it('logs who changed what through the admin screens', function () {
    $log = auditLog();
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 'q-1']])]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin());
    app('router')->getRoutes()->refreshNameLookups();

    $this->actingAs((new User)->forceFill(['id' => 42]))->from('/admin')->post('/flutterwave/chargebacks/1599/accept', ['comment' => 'Never shipped'])->assertRedirect('/admin');
    $this->actingAs((new User)->forceFill(['id' => 42]))->from('/admin')->post('/flutterwave/fx/quotes', ['base' => 'NGN', 'target' => 'USD', 'quantity' => 2000000]);

    $lines = file_get_contents($log);
    expect($lines)->toContain('Flutterwave Audit::chargeback.accept succeeded')
        ->toContain('"chargeback_id":"1599"')
        ->toContain('"user_id":42')
        ->toContain('"via":"web"')
        ->toContain('Flutterwave Audit::fx.quote succeeded')
        ->toContain('"quote_id":"q-1"');

    @unlink($log);
});

it('logs failed admin actions too', function () {
    $log = auditLog();
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Chargeback already resolved'], 400)]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin());
    app('router')->getRoutes()->refreshNameLookups();

    $this->actingAs((new User)->forceFill(['id' => 42]))->from('/admin')->post('/flutterwave/chargebacks/1599/accept', ['comment' => 'Refund']);

    expect(file_get_contents($log))->toContain('Flutterwave Audit::chargeback.accept failed')->toContain('Chargeback already resolved');
    @unlink($log);
});

it('logs commands that move money as console actions', function () {
    $log = auditLog();
    Http::fake(['*' => Http::response(['status' => 'success', 'message' => 'Chargeback accepted', 'data' => []])]);

    Artisan::call('flutterwave:accept-chargeback', ['id' => '1599', '--comment' => 'Refund', '--force' => true]);

    expect(file_get_contents($log))->toContain('Flutterwave Audit::chargeback.accept succeeded')->toContain('"via":"console"')->toContain('"console_user":');
    @unlink($log);
});

it('keeps the admin page out of caches', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []])]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin());
    app('router')->getRoutes()->refreshNameLookups();

    $response = $this->actingAs((new User)->forceFill(['id' => 1]))->get('/flutterwave')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('warns when the app runs on an ip address', function () {
    URL::forceRootUrl('http://127.0.0.1:8000');

    Artisan::call('flutterwave:doctor', ['--offline' => true]);

    expect(Artisan::output())->toContain("checkout doesn't load on an IP address like 127.0.0.1")->toContain("Whitelist this server's IP");
});
