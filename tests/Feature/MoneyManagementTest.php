<?php

use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

function moneyAdmin(): void
{
    Route::middleware('web')->group(fn () => Route::flutterwaveAdmin('admin/payments'));
    app('router')->getRoutes()->refreshNameLookups();
}

function asAdmin()
{
    Gate::define('flutterwave.manage', fn (User $user) => true);

    return test()->actingAs((new User)->forceFill(['id' => 7]))->from('/admin/payments');
}

// ---------------------------------------------------------------- services

it('sends refund comments and a callback url', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 8612]])]);

    Flutterwave::refund(908790, 5000, ['comments' => 'Damaged on delivery', 'callbackurl' => 'https://shop.test/refunds/callback']);
    Flutterwave::refund(908791);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/908790/refund' && $request->data() === ['amount' => 5000, 'comments' => 'Damaged on delivery', 'callbackurl' => 'https://shop.test/refunds/callback']);
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/908791/refund' && $request->data() === []);
});

it('sends the resend webhook wait flag as a query parameter', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => null])]);

    Flutterwave::transactions()->resendFailedHooks(4975363);
    Flutterwave::transactions()->resendFailedHooks(4975363, true);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/4975363/resend-hook' && $request->data() === []);
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/4975363/resend-hook?wait=1');
});

it('defaults the transaction list to the last 30 days, as flutterwave requires dates', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []])]);

    Flutterwave::transactions()->all(['status' => 'failed']);
    Flutterwave::transactions()->fee(10000, 'ngn', 'card');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), FLW_API.'transactions?')
        && $request['status'] === 'failed' && $request['to'] === date('Y-m-d') && $request['from'] === date('Y-m-d', strtotime('-30 days')));
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/fee?amount=10000&currency=NGN&payment_type=card');
});

it('needs a beneficiary name and passes the bank name', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 3644]])]);

    expect(fn () => Flutterwave::beneficiaries()->create('0690000034', '044'))->toThrow(InvalidArgument::class, "account holder's name");
    Flutterwave::beneficiaries()->create('0690000034', '044', 'Ade Bond', 'ngn', 'ACCESS BANK NIGERIA');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->data() === ['account_number' => '0690000034', 'account_bank' => '044', 'beneficiary_name' => 'Ade Bond', 'currency' => 'NGN', 'bank_name' => 'ACCESS BANK NIGERIA']);
});

it('lists banks for a dropdown, sorted and cached', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
        ['id' => 2, 'code' => '058', 'name' => 'Guaranty Trust Bank'],
        ['id' => 1, 'code' => '044', 'name' => ' Access Bank '],
        ['id' => 3, 'code' => '044', 'name' => 'Access Bank'],
        ['id' => 4, 'code' => '', 'name' => 'Broken'],
    ]])]);

    $first = Flutterwave::banks()->options('ng');
    $second = Flutterwave::banks()->options('NG');

    expect($first)->toBe([['code' => '044', 'name' => 'Access Bank'], ['code' => '058', 'name' => 'Guaranty Trust Bank']])->and($second)->toBe($first);
    Http::assertSentCount(1);
});

// ---------------------------------------------------------------- panels

it('builds the transactions panel with timeline, fee and actions', function () {
    Http::fake([
        FLW_API.'transactions/989226/events' => Http::response(['status' => 'success', 'data' => [['note' => 'Pay button clicked', 'actor' => 'h0vkard@flw.ext', 'action' => 'clicked', 'created_at' => '2026-10-13T22:09:58.001Z', 'secret' => 'x']]]),
        FLW_API.'transactions/fee*' => Http::response(['status' => 'success', 'data' => ['charge_amount' => 10140, 'fee' => 140, 'currency' => 'NGN']]),
        FLW_API.'transactions*' => Http::response(['status' => 'success', 'meta' => ['page_info' => ['total' => 2, 'current_page' => 1, 'total_pages' => 1]], 'data' => [
            ['id' => '989226', 'tx_ref' => 'ORDER-1', 'amount' => 1000, 'currency' => 'NGN', 'status' => 'successful', 'payment_type' => 'card', 'customer_name' => 'Yemi Desola', 'customer_email' => 'yemi@example.com', 'created_at' => '2026-10-13T22:06:45.000Z'],
            ['id' => '989227', 'tx_ref' => 'ORDER-2', 'amount' => 500, 'currency' => 'NGN', 'status' => 'failed', 'customer' => ['name' => 'Ada', 'email' => 'ada@example.com']],
        ]]),
    ]);
    moneyAdmin();

    $panel = Flutterwave::transactions()->panel(['status' => 'successful', 'transaction' => '989226', 'fee_amount' => '10000', 'fee_currency' => 'NGN', 'ignored' => 'x']);

    expect($panel['error'])->toBeNull()
        ->and($panel['filters'])->toMatchArray(['status' => 'successful'])->not->toHaveKey('ignored')
        ->and($panel['items'][0])->toMatchArray(['id' => '989226', 'customer_email' => 'yemi@example.com', 'status' => 'successful'])
        ->and($panel['items'][0]['urls']['refund'])->toEndWith('/admin/payments/transactions/989226/refund')
        ->and($panel['items'][1]['customer_email'])->toBe('ada@example.com')
        ->and($panel['items'][1]['urls']['refund'])->toBeNull()
        ->and($panel['timeline']['events'][0])->toBe(['note' => 'Pay button clicked', 'actor' => 'h0vkard@flw.ext', 'action' => 'clicked', 'created_at' => '2026-10-13T22:09:58.001Z'])
        ->and($panel['fee'])->toMatchArray(['fee' => 140, 'charge_amount' => 10140, 'error' => null]);
});

it('builds the refunds, settlements and beneficiaries panels', function () {
    Http::fake([
        FLW_API.'refunds*' => Http::response(['status' => 'success', 'data' => [['id' => 258505, 'amount_refunded' => 150, 'status' => 'completed-mpgs', 'transaction_id' => 263746647, 'created_at' => '2026-06-26T13:07:15.000Z']]]),
        FLW_API.'settlements/41497*' => Http::response(['status' => 'success', 'data' => ['id' => 41497, 'status' => 'completed', 'currency' => 'NGN', 'net_amount' => 49850, 'merchant_email' => 'private@example.com', 'transactions' => [['id' => 984411, 'tx_ref' => 'Rave-1', 'settlement_amount' => 9950, 'rrn' => 'N/A']]]]),
        FLW_API.'settlements*' => Http::response(['status' => 'success', 'data' => [['id' => 5032128, 'status' => 'flagged', 'currency' => 'NGN', 'net_amount' => 200, 'flag_message' => 'No settlement account', 'merchant_email' => 'private@example.com']]]),
        FLW_API.'beneficiaries*' => Http::response(['status' => 'success', 'data' => [['id' => 3768, 'account_number' => '0690000040', 'bank_code' => '044', 'full_name' => 'Alexis Sanchez', 'bank_name' => 'ACCESS BANK NIGERIA']]]),
        FLW_API.'banks/NG' => Http::response(['status' => 'success', 'data' => [['code' => '044', 'name' => 'Access Bank']]]),
    ]);
    moneyAdmin();

    $refunds = Flutterwave::refunds()->panel(['status' => 'completed']);
    $settlements = Flutterwave::settlements()->panel(['settlement' => '41497']);
    $beneficiaries = Flutterwave::beneficiaries()->panel();

    expect($refunds['items'][0])->toMatchArray(['id' => 258505, 'amount_refunded' => 150, 'transaction_id' => 263746647])
        ->and($settlements['items'][0])->toMatchArray(['flag_message' => 'No settlement account', 'net_amount' => 200])->not->toHaveKey('merchant_email')
        ->and($settlements['selected'])->toMatchArray(['id' => 41497, 'net_amount' => 49850])->not->toHaveKey('merchant_email')
        ->and($settlements['selected']['transactions'][0])->toBe(['id' => 984411, 'tx_ref' => 'Rave-1', 'settlement_amount' => 9950])
        ->and($beneficiaries['items'][0])->toMatchArray(['full_name' => 'Alexis Sanchez', 'bank_code' => '044'])
        ->and($beneficiaries['items'][0]['urls']['delete'])->toEndWith('/admin/payments/beneficiaries/3768')
        ->and($beneficiaries['banks'])->toBe([['code' => '044', 'name' => 'Access Bank']])
        ->and($beneficiaries['urls']['create'])->toEndWith('/admin/payments/beneficiaries');
});

it('returns panel errors instead of throwing', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid authorization key'], 401)]);

    expect(Flutterwave::transactions()->panel()['error'])->toBe('Invalid authorization key')
        ->and(Flutterwave::refunds()->panel()['error'])->toBe('Invalid authorization key')
        ->and(Flutterwave::settlements()->panel()['error'])->toBe('Invalid authorization key')
        ->and(Flutterwave::beneficiaries()->panel()['error'])->toBe('Invalid authorization key');
});

// ---------------------------------------------------------------- admin actions

it('refunds a transaction in full or in part', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['id' => 8612]])]);
    moneyAdmin();

    asAdmin()->post('/admin/payments/transactions/908790/refund', ['comment' => 'Damaged'])->assertRedirect('/admin/payments')->assertSessionHas('flutterwave.status', 'Transaction 908790 is being refunded in full.');
    asAdmin()->post('/admin/payments/transactions/908791/refund', ['amount' => '2500'])->assertSessionHas('flutterwave.status', 'A refund of 2500 for transaction 908791 has started.');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/908790/refund' && $request->data() === ['comments' => 'Damaged']);
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'transactions/908791/refund' && $request->data() === ['amount' => '2500']);
});

it('shows why a refund failed', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Refund amount exceeds transaction amount'], 400)]);
    moneyAdmin();

    asAdmin()->post('/admin/payments/transactions/908790/refund', ['amount' => '99999'])->assertSessionHasErrors(['flutterwave' => 'Refund amount exceeds transaction amount']);
    asAdmin()->post('/admin/payments/transactions/908790/refund', ['amount' => '-5'])->assertSessionHasErrors('amount');
});

it('resends a webhook from the admin', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'message' => 'hook sent successfully', 'data' => null])]);
    moneyAdmin();

    asAdmin()->post('/admin/payments/transactions/4975363/resend-webhook')->assertSessionHas('flutterwave.status', 'The webhook for transaction 4975363 was sent again.');
});

it('saves a beneficiary, using the name on the account when none is given', function () {
    Http::fake([
        FLW_API.'accounts/resolve' => Http::response(['status' => 'success', 'data' => ['account_number' => '0690000032', 'account_name' => 'Pastor Bright']]),
        FLW_API.'beneficiaries' => Http::response(['status' => 'success', 'data' => ['id' => 2923]]),
    ]);
    moneyAdmin();

    asAdmin()->post('/admin/payments/beneficiaries', ['account_bank' => '044', 'account_number' => '0690000032'])
        ->assertRedirect('/admin/payments')
        ->assertSessionHas('flutterwave.status', 'Saved Pastor Bright as a beneficiary.');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'beneficiaries' && $request['beneficiary_name'] === 'Pastor Bright' && $request['account_bank'] === '044');
});

it('reports an account it cannot resolve, and deletes beneficiaries', function () {
    Http::fake([
        FLW_API.'accounts/resolve' => Http::response(['status' => 'error', 'message' => 'Sorry, that account number is invalid', 'data' => null], 400),
        FLW_API.'beneficiaries/3768' => Http::response(['status' => 'success', 'message' => 'Beneficiary deleted', 'data' => 'Deleted']),
    ]);
    moneyAdmin();

    asAdmin()->post('/admin/payments/beneficiaries', ['account_bank' => '044', 'account_number' => '0123456789'])
        ->assertSessionHasErrors(['account_number' => "Couldn't find the name on that account: Sorry, that account number is invalid"]);
    asAdmin()->delete('/admin/payments/beneficiaries/3768')->assertSessionHas('flutterwave.status', 'Beneficiary deleted.');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === FLW_API.'beneficiaries/3768');
});

it('keeps the new actions behind the ability', function () {
    Http::fake();
    moneyAdmin();
    $user = (new User)->forceFill(['id' => 7]);

    $this->actingAs($user)->post('/admin/payments/transactions/1/refund')->assertForbidden();
    $this->actingAs($user)->post('/admin/payments/transactions/1/resend-webhook')->assertForbidden();
    $this->actingAs($user)->post('/admin/payments/beneficiaries', ['account_bank' => '044', 'account_number' => '0690000032'])->assertForbidden();
    $this->actingAs($user)->delete('/admin/payments/beneficiaries/1')->assertForbidden();

    Http::assertNothingSent();
});

// ---------------------------------------------------------------- blade components and admin sections

it('renders a bank dropdown with the code as each value', function () {
    $banks = array_map(fn (int $i) => ['code' => str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'name' => "Bank {$i}"], range(1, 15));

    $html = Blade::render('<x-flutterwave-bank-select :banks="$banks" selected="007" />', ['banks' => [['code' => '044', 'name' => 'Access Bank'], ...$banks]]);

    expect($html)->toContain('name="account_bank"')
        ->toContain('<option value="044" >Access Bank</option>')
        ->toContain('<option value="007" selected>Bank 7</option>')
        ->toContain('Search 16 banks')
        ->and(Blade::render('<x-flutterwave-bank-select :banks="$banks" name="bank" />', ['banks' => [['code' => '044', 'name' => 'Access Bank']]]))
        ->toContain('name="bank"')->not->toContain('data-flw-bank-search');
});

it('loads the banks itself and explains when it cannot', function () {
    Http::fake([FLW_API.'banks/GH' => Http::response(['status' => 'success', 'data' => [['code' => 'GH190100', 'name' => 'Absa Bank Ghana']]])]);
    expect(Blade::render('<x-flutterwave-bank-select country="GH" />'))->toContain('<option value="GH190100" >Absa Bank Ghana</option>');

    Http::fake([FLW_API.'banks/KE' => Http::response(['status' => 'error', 'message' => 'Country not supported'], 400)]);
    expect(Blade::render('<x-flutterwave-bank-select country="KE" />'))->toContain('load the list of banks: Country not supported')->toContain('disabled');
});

it('renders the money management sections of the admin page', function () {
    Http::fake([
        FLW_API.'transactions*' => Http::response(['status' => 'success', 'data' => [['id' => '989226', 'tx_ref' => 'ORDER-1', 'amount' => 1000, 'currency' => 'NGN', 'status' => 'successful', 'customer_name' => 'Yemi Desola', 'customer_email' => 'yemi@example.com', 'created_at' => '2026-10-13T22:06:45.000Z']]]),
        FLW_API.'refunds*' => Http::response(['status' => 'success', 'data' => [['id' => 258505, 'amount_refunded' => 150, 'status' => 'completed-mpgs', 'transaction_id' => 263746647, 'comment' => 'Damaged']]]),
        FLW_API.'settlements*' => Http::response(['status' => 'success', 'data' => [['id' => 5032128, 'status' => 'flagged', 'currency' => 'NGN', 'net_amount' => 200, 'transaction_count' => 2, 'flag_message' => 'No settlement account']]]),
        FLW_API.'beneficiaries*' => Http::response(['status' => 'success', 'data' => [['id' => 3768, 'account_number' => '0690000040', 'bank_code' => '044', 'full_name' => 'Alexis Sanchez', 'bank_name' => 'ACCESS BANK NIGERIA']]]),
        FLW_API.'banks/NG' => Http::response(['status' => 'success', 'data' => [['code' => '044', 'name' => 'Access Bank']]]),
    ]);
    moneyAdmin();

    asAdmin()->get('/admin/payments?section=transactions&status=successful')->assertOk()
        ->assertSee('NGN 1,000.00')->assertSee('Yemi Desola')->assertSee('Resend webhook')->assertSee('Refunds can’t be undone', false)
        ->assertSee('name="section" value="transactions"', false)
        ->assertSee('Check a fee');
    asAdmin()->get('/admin/payments?section=refunds')->assertOk()->assertSee('263746647')->assertSee('Completed')->assertSee('Damaged');
    asAdmin()->get('/admin/payments?section=settlements')->assertOk()->assertSee('NGN 200.00')->assertSee('No settlement account')->assertSee('2 payments');
    asAdmin()->get('/admin/payments?section=beneficiaries')->assertOk()->assertSee('Alexis Sanchez')->assertSee('<option value="044" >Access Bank</option>', false)->assertSee('Leave empty to use the name on the account');

    // Each section only calls the API it needs.
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'balances'));
});

it('shows a transaction timeline and a fee estimate', function () {
    Http::fake([
        FLW_API.'transactions/989226/events' => Http::response(['status' => 'success', 'data' => [['note' => 'Pay button clicked', 'created_at' => '2026-10-13T22:09:58.001Z']]]),
        FLW_API.'transactions/fee*' => Http::response(['status' => 'success', 'data' => ['charge_amount' => 10140, 'fee' => 140, 'stamp_duty_fee' => 50, 'currency' => 'NGN']]),
        FLW_API.'transactions*' => Http::response(['status' => 'success', 'data' => [['id' => '989226', 'tx_ref' => 'ORDER-1', 'amount' => 1000, 'currency' => 'NGN', 'status' => 'successful']]]),
    ]);
    moneyAdmin();

    asAdmin()->get('/admin/payments?section=transactions&transaction=989226&fee_amount=10000&fee_currency=NGN')->assertOk()
        ->assertSee('Pay button clicked')->assertSee('Hide timeline')
        ->assertSee('Flutterwave’s fee is <strong>NGN 140.00</strong>, so the customer is charged NGN 10,140.00, including NGN 50.00 stamp duty.', false);
});

it('shows a settlement breakdown', function () {
    Http::fake([
        FLW_API.'settlements/41497*' => Http::response(['status' => 'success', 'data' => ['id' => 41497, 'status' => 'completed', 'currency' => 'NGN', 'gross_amount' => 50800, 'app_fee' => 950, 'net_amount' => 49850, 'transactions' => [['tx_ref' => 'Rave-1', 'customer_email' => 'h0vkard@flw.ext', 'charged_amount' => 10140, 'app_fee' => 190, 'settlement_amount' => 9950]]]]),
        FLW_API.'settlements*' => Http::response(['status' => 'success', 'data' => [['id' => 41497, 'status' => 'completed', 'currency' => 'NGN', 'net_amount' => 49850, 'transaction_count' => 5]]]),
    ]);
    moneyAdmin();

    asAdmin()->get('/admin/payments?section=settlements&settlement=41497')->assertOk()
        ->assertSee('NGN 50,800.00')->assertSee('NGN 950.00')->assertSee('NGN 49,850.00')->assertSee('Rave-1')->assertSee('9,950.00')->assertSee('Hide details');
});

it('serves the stylesheet and fonts for the blade components, and nothing else', function () {
    $css = $this->get('/flutterwave/assets/flutterwave.css?v=2.3.0')->assertOk();
    expect($css->headers->get('Content-Type'))->toStartWith('text/css')
        ->and($css->headers->get('Cache-Control'))->toContain('immutable');
    expect(file_get_contents($css->baseResponse->getFile()->getPathname()))->toContain('--flw-orange: #ff9b00')->toContain('url("./fonts/Millik.woff2")');

    expect($this->get('/flutterwave/assets/fonts/Moderat-Regular.woff2')->assertOk()->headers->get('Content-Type'))->toBe('font/woff2');
    $this->get('/flutterwave/assets/../../.env')->assertNotFound();
    $this->get('/flutterwave/assets/fonts/../flutterwave.css')->assertNotFound();
    $this->get('/flutterwave/assets/Services/Banks.php')->assertNotFound();
});

it('links the stylesheet once however many components a page has', function () {
    $html = Blade::render('<x-flutterwave-bank-select :banks="$banks" /><x-flutterwave-bank-select :banks="$banks" name="other" />', ['banks' => [['code' => '044', 'name' => 'Access Bank']]]);

    expect(substr_count($html, 'flutterwave.css'))->toBe(1)->and($html)->toContain('class="flw-select"')->toContain('flw-select__chevron');
});
