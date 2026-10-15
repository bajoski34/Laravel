<?php

use Flutterwave\Payments\Notifications\ChargebackRaised;
use Flutterwave\Payments\Notifications\TransferFailed;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

function chargebackHook(string $event = 'chargeback.initiated', array $data = []): array
{
    return ['event' => $event, 'data' => ['id' => 1189808, 'flw_ref' => 'SZFY85201680612767705172', 'tx_ref' => 'ORDER-1088', 'amount' => 45000, 'comment' => 'Customer says the order never arrived', 'status' => substr($event, 11), 'due_date' => '2026-10-20T15:59:59.000Z', ...$data]];
}

function registerAdmin(bool $page = true): void
{
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin('admin/payments', page: $page));
    app('router')->getRoutes()->refreshNameLookups();
}

it('emails the team about a new chargeback when configured', function () {
    config(['flutterwave.notify.chargebacks' => 'disputes@shop.test, owner@shop.test']);
    Notification::fake();

    $this->postJson('/flutterwave/webhook', chargebackHook(), ['verif-hash' => 'my-secret-hash'])->assertOk();
    $this->postJson('/flutterwave/webhook', chargebackHook('chargeback.lost'), ['verif-hash' => 'my-secret-hash'])->assertOk();

    Notification::assertSentOnDemandTimes(ChargebackRaised::class, 1);
    Notification::assertSentOnDemand(ChargebackRaised::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['disputes@shop.test', 'owner@shop.test']);
});

it('emails the team about a failed payout when configured', function () {
    config(['flutterwave.notify.failed_transfers' => 'finance@shop.test']);
    Notification::fake();
    $hook = fn (string $status) => ['event' => 'transfer.completed', 'data' => ['id' => 77, 'reference' => "PAYOUT-{$status}", 'status' => $status, 'amount' => 5500, 'currency' => 'NGN']];

    $this->postJson('/flutterwave/webhook', $hook('SUCCESSFUL'), ['verif-hash' => 'my-secret-hash'])->assertOk();
    $this->postJson('/flutterwave/webhook', $hook('FAILED'), ['verif-hash' => 'my-secret-hash'])->assertOk();

    Notification::assertSentOnDemandTimes(TransferFailed::class, 1);
});

it('sends nothing unless addresses are configured', function () {
    Notification::fake();

    $this->postJson('/flutterwave/webhook', chargebackHook(), ['verif-hash' => 'my-secret-hash'])->assertOk();

    Notification::assertNothingSent();
});

it('writes a chargeback email with the deadline and a link to the admin page', function () {
    registerAdmin();

    $mail = (new ChargebackRaised(chargebackHook()['data']))->toMail(new AnonymousNotifiable);
    $text = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

    expect($mail->subject)->toBe('Chargeback on ORDER-1088: respond by 20 Oct 2026, 15:59 UTC')
        ->and($text)->toContain('for 45,000.00')->toContain('Reason given: Customer says the order never arrived')->toContain('JPEG, PNG or PDF under 8MB')
        ->and($mail->actionUrl)->toEndWith('/admin/payments')
        ->and((new ChargebackRaised(chargebackHook()['data'], ['mail', 'database']))->via(null))->toBe(['mail', 'database'])
        ->and((new ChargebackRaised(chargebackHook()['data']))->toArray(null))->toMatchArray(['chargeback_id' => 1189808, 'tx_ref' => 'ORDER-1088']);
});

it('writes a failed payout email with the reason', function () {
    $mail = (new TransferFailed(['id' => 77, 'reference' => 'PAYOUT-9', 'amount' => 5500, 'currency' => 'NGN', 'full_name' => 'Jane Doe', 'account_number' => '0690000040', 'complete_message' => 'DISBURSE FAILED: Insufficient funds in customer wallet']))->toMail(null);
    $text = implode(' ', $mail->introLines);

    expect($mail->subject)->toBe('Payout PAYOUT-9 failed')
        ->and($mail->level)->toBe('error')
        ->and($text)->toContain('The NGN 5,500.00 payout to Jane Doe (0690000040) failed')->toContain('Insufficient funds in customer wallet')->toContain('transfers()->retry(77)');
});

it('shows the admin page to allowed users', function () {
    Http::fake([
        FLW_API.'balances' => Http::response(['status' => 'success', 'data' => [['currency' => 'NGN', 'available_balance' => 250000, 'ledger_balance' => 260000], ['currency' => 'KES', 'available_balance' => 0, 'ledger_balance' => 0]]]),
        FLW_API.'chargebacks*' => Http::response(['status' => 'success', 'data' => [['id' => 1612, 'amount' => 45000, 'status' => 'initiated', 'tx_ref' => 'ORDER-1088', 'flw_ref' => 'FLW-1', 'due_date' => date(DATE_ATOM, time() + 6000), 'meta' => ['history' => []]]]]),
    ]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    registerAdmin();

    $this->actingAs((new User)->forceFill(['id' => 1]))->get('/admin/payments')
        ->assertOk()
        ->assertSee('Payments')
        ->assertSee('250,000.00')
        ->assertSee('260,000.00 including pending')
        ->assertDontSee('KES')
        ->assertSee('ORDER-1088')
        ->assertSee('Respond within 1 h 40 min', false)
        ->assertSee('Convert currency')
        ->assertSee('Test mode');

    Http::assertSent(fn ($request) => $request->url() === FLW_API.'chargebacks?status=initiated');
});

it('shows every chargeback on the all tab', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []])]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    registerAdmin();

    $this->actingAs((new User)->forceFill(['id' => 1]))->get('/admin/payments?chargebacks=all')->assertOk()->assertSee('No chargebacks.');

    Http::assertSent(fn ($request) => $request->url() === FLW_API.'chargebacks');
});

it('keeps the admin page closed without the ability, and can leave it out', function () {
    Http::fake();
    registerAdmin();

    $this->actingAs((new User)->forceFill(['id' => 1]))->get('/admin/payments')->assertForbidden();
    Http::assertNothingSent();
});

it('can register the actions without the page', function () {
    registerAdmin(page: false);

    expect(Route::has('flutterwave.admin'))->toBeFalse()->and(Route::has('flutterwave.fx.quote'))->toBeTrue();
});
