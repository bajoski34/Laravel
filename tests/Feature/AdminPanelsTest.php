<?php

use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

function adminRoutes(): void
{
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin('admin/flutterwave'));
    app('router')->getRoutes()->refreshNameLookups();
}

function admin(): User
{
    return (new User)->forceFill(['id' => 1]);
}

function chargebackApiRow(array $overrides = []): array
{
    return [
        'id' => 1599, 'amount' => 200, 'flw_ref' => 'FLW-MOCK-c23b', 'status' => 'initiated', 'stage' => 'new', 'comment' => 'Fraud dispute',
        'due_date' => date(DATE_ATOM, time() + 6 * 3600), 'transaction_id' => 9748327, 'tx_ref' => 'ORDER-1042',
        'meta' => ['uploaded_proof' => null, 'history' => [['action' => 'initiated', 'date' => '2026-10-15T04:55:14.000Z', 'description' => 'Debit and hold chargeback amount']]],
        ...$overrides,
    ];
}

it('registers no admin routes unless the app asks for them', function () {
    expect(Route::has('flutterwave.chargebacks.accept'))->toBeFalse()
        ->and(Route::has('flutterwave.fx.quote'))->toBeFalse();

    adminRoutes();

    expect(route('flutterwave.chargebacks.decline', 1599))->toEndWith('/admin/flutterwave/chargebacks/1599/decline')
        ->and(route('flutterwave.fx.trade'))->toEndWith('/admin/flutterwave/fx/trades');
});

it('builds the chargeback panel with countdowns and action urls', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'meta' => ['page_info' => ['total' => 2, 'current_page' => 1, 'total_pages' => 1]], 'data' => [
        chargebackApiRow(),
        chargebackApiRow(['id' => 1603, 'status' => 'Accepted']),
    ]])]);
    adminRoutes();

    $panel = Flutterwave::chargebacks()->panel(['status' => 'initiated']);

    expect($panel['error'])->toBeNull()
        ->and($panel['page']['total'])->toBe(2)
        ->and($panel['proof'])->toBe(['types' => ['jpg', 'jpeg', 'png', 'pdf'], 'max_bytes' => 8388608])
        ->and($panel['items'][0])->toMatchArray(['id' => 1599, 'status' => 'initiated', 'tx_ref' => 'ORDER-1042'])
        ->and($panel['items'][0]['respond_within'])->toBeGreaterThan(6 * 3600 - 10)->toBeLessThanOrEqual(6 * 3600)
        ->and($panel['items'][0]['urls']['accept'])->toEndWith('/admin/flutterwave/chargebacks/1599/accept')
        ->and($panel['items'][0]['history'][0]['description'])->toBe('Debit and hold chargeback amount')
        ->and($panel['items'][1])->toMatchArray(['status' => 'accepted', 'respond_within' => null, 'urls' => null]);
});

it('leaves out chargeback actions when the routes are not registered', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [chargebackApiRow()]])]);

    expect(Flutterwave::chargebacks()->panel()['items'][0]['urls'])->toBeNull();
});

it('returns api errors in the panel instead of throwing', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid id.', 'data' => null], 400)]);

    expect(Flutterwave::chargebacks()->panel())->toMatchArray(['items' => [], 'error' => 'Invalid id.'])
        ->and(Flutterwave::fx()->panel('nope'))->toMatchArray(['quote' => null, 'error' => 'Invalid id.']);
});

it('builds the fx panel for a quote and a trade', function () {
    Http::fake([
        FLW_API.'rfq/570e21b4' => Http::response(['status' => 'success', 'data' => [
            'id' => '570e21b4', 'reference' => 'd764', 'quantity' => 1617759, 'instrument' => 'NGN/USD', 'status' => 'READY',
            'quote' => ['best_available_rate' => 0.000659, 'approved_quantity' => 1517759, 'total_value' => 1000.203181, 'expiry' => gmdate('Y-m-d\TH:i:s.000\Z', time() + 240)],
            'complete_message' => 'Your RFQ is ready',
        ]]),
        FLW_API.'trade/08e27e03' => Http::response(['status' => 'success', 'data' => [
            'id' => '08e27e03', 'quantity' => 1617759, 'approved_quantity' => 1517759, 'price' => 1000.203181, 'instrument' => 'NGN/USD', 'status' => 'PENDING', 'recipient' => ['name' => 'MythCo.'],
        ]]),
    ]);
    adminRoutes();

    $panel = Flutterwave::fx()->panel('570e21b4', '08e27e03');

    expect($panel['quote'])->toMatchArray(['base' => 'NGN', 'target' => 'USD', 'rate' => '0.000659', 'approved_quantity' => 1517759, 'tradable' => true, 'pending' => false])
        ->and($panel['quote']['expires_in'])->toBeGreaterThan(230)->toBeLessThanOrEqual(240)
        ->and($panel['trade'])->toMatchArray(['status' => 'PENDING', 'sold' => 1517759, 'bought' => 1000.203181, 'recipient' => 'MythCo.', 'pending' => true])
        ->and($panel['pairs'])->toBe([['NGN', 'USD'], ['GHS', 'USD'], ['USD', 'NGN']])
        ->and($panel['urls']['quote'])->toEndWith('/admin/flutterwave/fx/quotes');
});

it('refuses every action until the app defines the flutterwave.manage ability', function () {
    Http::fake();
    adminRoutes();

    $this->actingAs(admin())->post('/admin/flutterwave/chargebacks/1599/accept', ['comment' => 'Refund'])->assertForbidden();
    $this->actingAs(admin())->post('/admin/flutterwave/fx/quotes', ['base' => 'NGN', 'target' => 'USD', 'quantity' => 2000000])->assertForbidden();

    Http::assertNothingSent();
});

it('refuses guests even when the ability is defined', function () {
    Http::fake();
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->post('/admin/flutterwave/fx/trades', ['quote_id' => 'q', 'narration' => 'x'])->assertForbidden();
    Http::assertNothingSent();
});

it('accepts and declines chargebacks for allowed users', function () {
    Http::fake([
        FLW_API.'upload_image' => Http::response(['status' => 'success', 'data' => 'https://cdn.filestackcontent.com/UQbC']),
        FLW_API.'*' => Http::response(['status' => 'success', 'data' => []]),
    ]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->actingAs(admin())->from('/admin/disputes')
        ->post('/admin/flutterwave/chargebacks/1599/accept', ['comment' => 'Order was never shipped.'])
        ->assertRedirect('/admin/disputes')
        ->assertSessionHas('flutterwave.status', 'Chargeback 1599 accepted. The customer will be refunded.');

    $this->actingAs(admin())->from('/admin/disputes')
        ->post('/admin/flutterwave/chargebacks/1603/decline', ['comment' => 'Signed for.', 'proof' => UploadedFile::fake()->createWithContent('delivery.pdf', '%PDF-1.4 signed delivery note')])
        ->assertRedirect('/admin/disputes')
        ->assertSessionHas('flutterwave.status');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks/1599' && $request['action'] === 'accept');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks/1603' && $request['action'] === 'decline' && $request['prooflink'] === 'https://cdn.filestackcontent.com/UQbC');
});

it('validates chargeback responses before calling flutterwave', function () {
    Http::fake();
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->actingAs(admin())->from('/admin/disputes')->post('/admin/flutterwave/chargebacks/1599/accept', [])->assertSessionHasErrors('comment');
    $this->actingAs(admin())->from('/admin/disputes')
        ->post('/admin/flutterwave/chargebacks/1599/decline', ['comment' => 'x', 'proof' => UploadedFile::fake()->create('proof.docx', 10)])
        ->assertSessionHasErrors(['proof' => 'The proof must be a JPEG, PNG or PDF file.']);
    $this->actingAs(admin())->from('/admin/disputes')
        ->post('/admin/flutterwave/chargebacks/1599/decline', ['comment' => 'x', 'proof' => UploadedFile::fake()->create('scan.pdf', 8192, 'application/pdf')])
        ->assertSessionHasErrors(['proof' => 'The proof must be smaller than 8MB.']);

    Http::assertNothingSent();
});

it('shows the flutterwave error when an action fails', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Chargeback already resolved', 'data' => null], 400)]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->actingAs(admin())->from('/admin/disputes')
        ->post('/admin/flutterwave/chargebacks/1599/accept', ['comment' => 'Refund'])
        ->assertRedirect('/admin/disputes')
        ->assertSessionHasErrors(['flutterwave' => 'Chargeback already resolved']);
});

it('requests a quote and redirects back with its id', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => '570e21b4', 'status' => 'NEW']])]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->actingAs(admin())->from('/admin/treasury?tab=fx&fx_trade=old')
        ->post('/admin/flutterwave/fx/quotes', ['base' => 'ngn', 'target' => 'usd', 'quantity' => '2000000'])
        ->assertRedirect('/admin/treasury?tab=fx&fx_quote=570e21b4');

    Http::assertSent(fn (Request $request) => $request->data() === ['base_currency' => 'NGN', 'target_currency' => 'USD', 'quantity' => '2000000']);
});

it('trades a ready quote and redirects back with both ids', function () {
    Http::fake([
        FLW_API.'rfq/570e21b4' => Http::response(['status' => 'success', 'data' => ['id' => '570e21b4', 'status' => 'READY']]),
        FLW_API.'trades' => Http::response(['status' => 'success', 'data' => ['id' => '08e27e03', 'status' => 'NEW']]),
    ]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->actingAs(admin())->from('/admin/treasury?fx_quote=570e21b4')
        ->post('/admin/flutterwave/fx/trades', ['quote_id' => '570e21b4', 'narration' => 'Supplier payment'])
        ->assertRedirect('/admin/treasury?fx_quote=570e21b4&fx_trade=08e27e03');
});

it('keeps an expired quote from being traded', function () {
    Http::fake([FLW_API.'rfq/570e21b4' => Http::response(['status' => 'success', 'data' => ['id' => '570e21b4', 'status' => 'EXPIRED', 'complete_message' => 'Your RFQ is expired']])]);
    Gate::define('flutterwave.manage', fn (User $user) => true);
    adminRoutes();

    $this->actingAs(admin())->from('/admin/treasury?fx_quote=570e21b4')
        ->post('/admin/flutterwave/fx/trades', ['quote_id' => '570e21b4', 'narration' => 'Supplier payment'])
        ->assertRedirect('/admin/treasury?fx_quote=570e21b4')
        ->assertSessionHasErrors(['flutterwave' => 'Quote 570e21b4 is EXPIRED, not READY. Your RFQ is expired']);

    Http::assertSentCount(1);
});

function fxPanel(array $quote = [], ?array $trade = null, bool $routes = true): array
{
    return [
        'quote' => $quote === [] ? null : [
            'id' => '570e21b4', 'reference' => 'd764', 'status' => 'READY', 'base' => 'NGN', 'target' => 'USD', 'quantity' => 1617759, 'approved_quantity' => 1517759,
            'rate' => '0.000659', 'total_value' => 1000.203181, 'expires_at' => null, 'expires_in' => 245, 'message' => 'Your RFQ is ready', 'pending' => false, 'tradable' => true,
            ...$quote,
        ],
        'trade' => $trade,
        'error' => null,
        'pairs' => [['NGN', 'USD'], ['GHS', 'USD'], ['USD', 'NGN']],
        'urls' => $routes ? ['quote' => '/admin/flutterwave/fx/quotes', 'trade' => '/admin/flutterwave/fx/trades'] : null,
    ];
}

it('renders chargebacks in blade with forms for open ones', function () {
    // Due 6 h 30 min 30 s from now, so a second passing while the test runs can't change the minute shown.
    $due = date(DATE_ATOM, time() + 6 * 3600 + 30 * 60 + 30);
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [chargebackApiRow(['due_date' => $due]), chargebackApiRow(['id' => 1603, 'status' => 'won'])]])]);
    adminRoutes();

    $html = Blade::render('<x-flutterwave-chargebacks :panel="$panel" />', ['panel' => Flutterwave::chargebacks()->panel()]);

    expect($html)
        ->toContain('Needs a response')
        ->toContain('Respond within 6 h 30 min, or the customer is refunded automatically.')
        ->toContain('action="'.route('flutterwave.chargebacks.decline', 1599).'"')
        ->toContain('enctype="multipart/form-data"')
        ->toContain('accept=".jpg,.jpeg,.png,.pdf"')
        ->toContain('Won')
        ->toContain('Debit and hold chargeback amount')
        ->and(substr_count($html, 'name="_token"'))->toBe(2);
});

it('renders chargebacks read-only without the admin routes', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [chargebackApiRow()]])]);

    $html = Blade::render('<x-flutterwave-chargebacks :panel="$panel" />', ['panel' => Flutterwave::chargebacks()->panel()]);

    expect($html)->toContain('Needs a response')->not->toContain('<form');
});

it('shows the error from the last chargeback action in blade', function () {
    session()->put('errors', (new ViewErrorBag)->put('default', new MessageBag(['flutterwave' => 'Chargeback already resolved'])));

    $html = Blade::render('<x-flutterwave-chargebacks :panel="$panel" />', ['panel' => ['items' => [], 'page' => null, 'error' => null, 'proof' => ['types' => ['pdf'], 'max_bytes' => 1]]]);

    expect($html)->toContain('Chargeback already resolved')->toContain('No chargebacks.');
});

it('renders each fx step in blade', function () {
    $request = Blade::render('<x-flutterwave-fx :panel="$panel" />', ['panel' => fxPanel()]);
    $pending = Blade::render('<x-flutterwave-fx :panel="$panel" />', ['panel' => fxPanel(['status' => 'PROCESSING', 'pending' => true, 'tradable' => false])]);
    $ready = Blade::render('<x-flutterwave-fx :panel="$panel" />', ['panel' => fxPanel(['status' => 'READY'])]);
    $expired = Blade::render('<x-flutterwave-fx :panel="$panel" />', ['panel' => fxPanel(['status' => 'EXPIRED', 'tradable' => false])]);
    $settled = Blade::render('<x-flutterwave-fx :panel="$panel" />', ['panel' => fxPanel(['status' => 'READY'], ['id' => '08e27e03', 'status' => 'SETTLED', 'base' => 'NGN', 'target' => 'USD', 'sold' => 1517759, 'bought' => 1000.203181, 'recipient' => 'MythCo.', 'reference' => 'TRD-86ad', 'message' => null, 'pending' => false])]);

    expect($request)->toContain('action="/admin/flutterwave/fx/quotes"')->toContain('NGN to USD')->not->toContain('window.location.reload')
        ->and($pending)->toContain('Getting a rate…')->toContain('window.location.reload')
        ->and($ready)->toContain('NGN 1,517,759.00')->toContain('USD 1,000.20')->toContain('data-flw-countdown="245"')->toContain('Held for 4:05')
        ->toContain('Flutterwave approved NGN 1,517,759.00 of the NGN 1,617,759.00 you asked for.')->toContain('name="quote_id" value="570e21b4"')
        ->and($expired)->toContain('This rate has expired')->not->toContain('action="/admin/flutterwave/fx/trades"')
        ->and($settled)->toContain('Sold NGN 1,517,759.00 for USD 1,000.20, paid to MythCo.')->toContain('Start another conversion')->not->toContain('window.location.reload');
});

it('renders the fx converter without actions when the routes are missing', function () {
    expect(Blade::render('<x-flutterwave-fx :panel="$panel" />', ['panel' => fxPanel(routes: false)]))->toContain('Add Route::flutterwaveAdmin()')->not->toContain('<form');
});
