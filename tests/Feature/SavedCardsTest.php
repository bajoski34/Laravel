<?php

use Flutterwave\Payments\Billable;
use Flutterwave\Payments\Data\SavedCard;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class BillableCustomer extends Model
{
    use Billable;

    protected $table = 'users';

    protected $guarded = [];
}

function cardTransaction(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 4975363, 'status' => 'successful', 'payment_type' => 'card',
        'customer' => ['email' => 'jane@example.com'],
        'card' => ['first_6digits' => '553188', 'last_4digits' => '2950', 'type' => 'MASTERCARD', 'expiry' => '09/32', 'token' => 'flw-t1nf-93da56b24f8ee332304cd2eea40a1fc4-m03k'],
    ], $overrides);
}

function billableTable(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->timestamps();
    });

    (require __DIR__.'/../../src/database/migrations/add_flutterwave_card_columns.php.stub')->up();
}

it('charges a saved card with the defaults filled in', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['status' => 'successful']])]);

    Flutterwave::cards()->charge('flw-t1nf-abc', 'jane@example.com', 5000, ['narration' => 'October plan']);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'tokenized-charges'
        && $request['token'] === 'flw-t1nf-abc'
        && $request['email'] === 'jane@example.com'
        && $request['amount'] === 5000
        && $request['currency'] === 'NGN'
        && $request['country'] === 'NG'
        && str_starts_with($request['tx_ref'], 'LARAVEL-')
        && $request['redirect_url'] === 'https://shop.test/flutterwave/payment/callback'
        && $request['narration'] === 'October plan'
        && ! isset($request['preauthorize']));
});

it('preauthorizes and settles holds', function () {
    $fake = Flutterwave::fake();

    $hold = Flutterwave::cards()->preauthorize('flw-t1nf-abc', 'jane@example.com', 7500);
    Flutterwave::preauth()->capture('FLW-MOCK-PREAUTH-78ac', 5000);
    Flutterwave::preauth()->void('FLW-MOCK-PREAUTH-90bd');
    Flutterwave::preauth()->refund('FLW-MOCK-PREAUTH-78ac', 1000);

    expect($hold['data']['status'])->toBe('pending')->and($hold['data']['charge_type'])->toBe('preauth');
    $fake->assertCardCharged(fn (array $charge) => $charge['preauthorize'] === true && $charge['amount'] === 7500)
        ->assertSent('POST', 'charges/FLW-MOCK-PREAUTH-78ac/capture', fn (array $data) => $data['amount'] === '5000')
        ->assertSent('POST', 'charges/FLW-MOCK-PREAUTH-90bd/void')
        ->assertSent('POST', 'charges/FLW-MOCK-PREAUTH-78ac/refund', fn (array $data) => $data['amount'] === '1000');
});

it('charges many saved cards with a retry strategy', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 130]])]);

    Flutterwave::cards()->chargeMany([
        ['token' => 'flw-t1', 'email' => 'a@example.com', 'amount' => 3000],
        ['token' => 'flw-t2', 'email' => 'b@example.com', 'amount' => 3500, 'currency' => 'ghs', 'country' => 'gh'],
    ], ['retry_interval' => 60], 'October renewals');
    Flutterwave::cards()->bulkStatus(130);
    Flutterwave::cards()->bulkTransactions(130);
    Flutterwave::cards()->updateToken('flw-t1', 'new@example.com', '08100000000', 'Jane', 'Doe');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'bulk-tokenized-charges'
        && $request['title'] === 'October renewals'
        && $request['retry_strategy'] === ['retry_interval' => 60, 'retry_amount_variable' => 100, 'retry_attempt_variable' => 2, 'last_retry_attempt' => 3]
        && count($request['bulk_data']) === 2
        && $request['bulk_data'][1]['currency'] === 'GHS'
        && ! isset($request['bulk_data'][0]['redirect_url']));
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'bulk-tokenized-charges/130');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'bulk-tokenized-charges/130/transactions');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === FLW_API.'tokens/flw-t1' && $request['email'] === 'new@example.com');
});

it('checks saved card charges before calling the api', function (string $method, array $arguments, string $message) {
    Http::fake();

    expect(fn () => Flutterwave::{$method === 'capture' ? 'preauth' : 'cards'}()->{$method}(...$arguments))->toThrow(InvalidArgument::class, $message);
    Http::assertNothingSent();
})->with([
    'no token' => ['charge', ['', 'jane@example.com', 100], 'A card token is required'],
    'no email' => ['charge', ['flw-t1', ' ', 100], 'tokens are tied to it'],
    'zero amount' => ['charge', ['flw-t1', 'jane@example.com', 0], 'greater than zero'],
    'empty bulk' => ['chargeMany', [[]], 'at least one charge'],
    'zero capture' => ['capture', ['FLW-1', 0], 'amount to capture must be greater than zero'],
]);

it('reads a saved card from a verified transaction', function () {
    $card = SavedCard::fromTransaction(cardTransaction());

    expect($card->token)->toBe('flw-t1nf-93da56b24f8ee332304cd2eea40a1fc4-m03k')
        ->and($card->email)->toBe('jane@example.com')
        ->and($card->label())->toBe('MASTERCARD ending 2950')
        ->and($card->tokenExpiresAt()->format('Y'))->toBe((string) ((int) date('Y') + 1))
        ->and($card->expiresWithin(30))->toBeFalse()
        ->and(SavedCard::fromTransaction(cardTransaction(['card' => ['expiry' => date('m/y')]]))->expiresWithin(31))->toBeTrue();

    expect(fn () => SavedCard::fromTransaction(['payment_type' => 'bank_transfer', 'customer' => ['email' => 'a@b.co']]))->toThrow(InvalidArgument::class, 'no card token');
});

it('saves a card on a billable model, encrypted and hidden', function () {
    billableTable();
    $user = BillableCustomer::create(['email' => 'jane@example.com']);

    $user->saveFlutterwaveCard(cardTransaction());
    $user->refresh();

    expect($user->hasFlutterwaveCard())->toBeTrue()
        ->and($user->flutterwaveCard()->label())->toBe('MASTERCARD ending 2950')
        ->and($user->flutterwaveCard()->savedAt)->not->toBeNull()
        ->and(DB::table('users')->value('flutterwave_card_token'))->not->toContain('flw-t1nf')
        ->and($user->toArray())->not->toHaveKey('flutterwave_card_token')
        ->and($user->toArray())->not->toHaveKey('flutterwave_card_email')
        ->and($user->toArray()['flutterwave_card_last4'])->toBe('2950');
});

it('charges and preauthorizes the saved card', function () {
    billableTable();
    $fake = Flutterwave::fake();
    $user = BillableCustomer::create(['email' => 'jane.doe@example.com']);
    $user->saveFlutterwaveCard(cardTransaction());

    $user->chargeSavedCard(5000, ['narration' => 'October plan']);
    $user->preauthorizeSavedCard(7500);

    // Charged with the email the card was saved with, not the account's current email.
    $fake->assertCardCharged(fn (array $charge) => $charge['email'] === 'jane@example.com' && $charge['amount'] === 5000 && $charge['token'] === 'flw-t1nf-93da56b24f8ee332304cd2eea40a1fc4-m03k')
        ->assertCardCharged(fn (array $charge) => ($charge['preauthorize'] ?? false) === true);
});

it('refuses to save or charge without a usable card', function () {
    billableTable();
    $user = BillableCustomer::create(['email' => 'jane@example.com']);

    expect(fn () => $user->saveFlutterwaveCard(cardTransaction(['status' => 'failed'])))->toThrow(InvalidArgument::class, 'successful payments')
        ->and(fn () => $user->chargeSavedCard(5000))->toThrow(InvalidArgument::class, 'no saved card');

    $user->saveFlutterwaveCard(cardTransaction());
    $user->forgetFlutterwaveCard();

    expect($user->refresh()->hasFlutterwaveCard())->toBeFalse();
});

it('publishes the billable migration once', function () {
    $published = fn () => glob(database_path('migrations/*_add_flutterwave_card_columns.php'));
    app()->useEnvironmentPath(sys_get_temp_dir().'/flw-missing-'.uniqid());

    $this->artisan('flutterwave:install', ['--billable' => true, '--no-interaction' => true])->expectsOutputToContain('Published the saved card migration')->assertSuccessful();
    $this->artisan('flutterwave:install', ['--billable' => true, '--no-interaction' => true])->expectsOutputToContain('already in database/migrations')->assertSuccessful();
    $files = $published();

    array_map('unlink', $files);
    @unlink(config_path('flutterwave.php'));

    expect($files)->toHaveCount(1);
});
