<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function chargebackRow(array $overrides = []): array
{
    return [
        'id' => 1599, 'amount' => 200, 'flw_ref' => 'FLW-MOCK-c23b', 'status' => 'initiated', 'stage' => 'new', 'comment' => 'Fraud dispute',
        'due_date' => '2026-10-20T16:52:03.000Z', 'transaction_id' => 9748327, 'tx_ref' => 'ORDER-1042',
        'meta' => ['uploaded_proof' => null, 'history' => [
            ['action' => 'initiated', 'date' => '2026-10-15T04:55:14.000Z', 'description' => 'Debit and hold chargeback amount'],
            ['action' => 'notification', 'date' => '2026-10-15T04:55:33.000Z', 'description' => 'Email Notification Sent'],
        ]],
        ...$overrides,
    ];
}

function readyQuote(array $overrides = []): array
{
    return [
        'id' => '570e21b4', 'reference' => 'd76409d8', 'quantity' => 1617759, 'instrument' => 'NGN/USD', 'status' => 'READY',
        'quote' => ['best_available_rate' => '0.000659', 'approved_quantity' => 1517759, 'total_value' => 1000.203181, 'expiry' => '2026-10-15T12:11:18.633Z'],
        'complete_message' => 'Your RFQ is ready',
        ...$overrides,
    ];
}

it('lists chargebacks and flags the ones that need a response', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'meta' => ['page_info' => ['total' => 2, 'current_page' => 1, 'total_pages' => 1]], 'data' => [
        chargebackRow(),
        chargebackRow(['id' => 1603, 'status' => 'accepted', 'tx_ref' => 'ORDER-1040']),
    ]])]);

    $this->artisan('flutterwave:chargebacks', ['--status' => 'INITIATED', '--from' => '2026-10-01', '--currency' => 'ngn'])
        ->expectsOutputToContain('ORDER-1042')
        ->expectsOutputToContain('Page 1 of 1, 2 in total.')
        ->expectsOutputToContain('1 chargeback needs a response before the due date.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks?status=initiated&from=2026-10-01&currency=NGN');
});

it('shows one chargeback with its history', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => [chargebackRow()]])]);

    $this->artisan('flutterwave:chargebacks', ['--ref' => 'FLW-MOCK-c23b'])
        ->expectsOutputToContain('Debit and hold chargeback amount')
        ->expectsOutputToContain('9748327 ORDER-1042')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks?flw_ref=FLW-MOCK-c23b');
});

it('says when there are no chargebacks', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => []])]);

    $this->artisan('flutterwave:chargebacks')->expectsOutputToContain('No chargebacks match.')->assertSuccessful();
});

it('accepts a chargeback after confirming', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'message' => 'Chargeback accepted', 'data' => []])]);

    $this->artisan('flutterwave:accept-chargeback', ['id' => '1599'])
        ->expectsQuestion('Why are you accepting this chargeback?', 'Order was never shipped.')
        ->expectsConfirmation("Accept chargeback 1599? The customer is refunded and this can't be undone.", 'yes')
        ->expectsOutputToContain('Chargeback accepted')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['action' => 'accept', 'comment' => 'Order was never shipped.']);
});

it('changes nothing when the confirmation is declined or there is no terminal', function () {
    Http::fake();

    $this->artisan('flutterwave:accept-chargeback', ['id' => '1599', '--comment' => 'Refund'])
        ->expectsConfirmation("Accept chargeback 1599? The customer is refunded and this can't be undone.", 'no')
        ->expectsOutputToContain('Nothing was changed.')
        ->assertFailed();

    $this->artisan('flutterwave:accept-chargeback', ['id' => '1599', '--comment' => 'Refund', '--no-interaction' => true])
        ->expectsOutputToContain('Pass --force to run this without a prompt.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('declines a chargeback by uploading the proof', function () {
    Http::fake([
        FLW_API.'upload_image' => Http::response(['status' => 'success', 'data' => 'https://cdn.filestackcontent.com/UQbC']),
        FLW_API.'chargebacks/1599' => Http::response(['status' => 'success', 'message' => 'Chargeback declined', 'data' => ['flw_ref' => 'FLW-MOCK-c23b']]),
    ]);
    $path = sys_get_temp_dir().'/'.uniqid('flw-').'-delivery.pdf';
    file_put_contents($path, '%PDF-1.4');

    $this->artisan('flutterwave:decline-chargeback', ['id' => '1599', '--proof' => $path, '--comment' => 'Signed for on delivery.', '--force' => true])
        ->expectsOutputToContain('Chargeback declined')
        ->expectsOutputToContain('flutterwave:chargebacks --ref=FLW-MOCK-c23b')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks/1599' && $request['prooflink'] === 'https://cdn.filestackcontent.com/UQbC');
    unlink($path);
});

it('declines with a proof link that was already uploaded', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'message' => 'Chargeback declined', 'data' => []])]);

    $this->artisan('flutterwave:decline-chargeback', ['id' => '1599', '--proof-link' => 'https://cdn.filestackcontent.com/UQbC', '--comment' => 'Signed for.', '--force' => true])
        ->assertSuccessful();

    Http::assertSentCount(1);
});

it('needs exactly one kind of proof to decline', function (array $options) {
    Http::fake();

    $this->artisan('flutterwave:decline-chargeback', ['id' => '1599', '--comment' => 'Signed for.', '--force' => true, ...$options])
        ->expectsOutputToContain('Pass either --proof with your evidence file or --proof-link')
        ->assertFailed();

    Http::assertNothingSent();
})->with([
    'neither' => [[]],
    'both' => [['--proof' => '/tmp/a.pdf', '--proof-link' => 'https://cdn.test/a']],
]);

it('prints the reason instead of a stack trace when proof is rejected', function () {
    Http::fake();

    $this->artisan('flutterwave:decline-chargeback', ['id' => '1599', '--proof' => '/nope/proof.docx', '--comment' => 'Signed for.', '--force' => true])
        ->expectsOutputToContain("doesn't exist or can't be read")
        ->assertFailed();
});

it('waits for an fx quote to be ready', function () {
    Http::fake([
        FLW_API.'rfq' => Http::response(['status' => 'success', 'data' => readyQuote(['status' => 'NEW', 'quote' => null])]),
        FLW_API.'rfq/570e21b4' => Http::sequence()
            ->push(['status' => 'success', 'data' => readyQuote(['status' => 'PROCESSING', 'quote' => null])])
            ->push(['status' => 'success', 'data' => readyQuote()]),
    ]);

    $this->artisan('flutterwave:fx-quote', ['base' => 'ngn', 'target' => 'usd', 'quantity' => '1617759', '--poll' => 0])
        ->expectsOutputToContain('READY')
        ->expectsOutputToContain('NGN 1,517,759.00')
        ->expectsOutputToContain('USD 1,000.20')
        ->expectsOutputToContain('flutterwave:fx-trade 570e21b4')
        ->assertSuccessful();

    Http::assertSentCount(3);
});

it('returns a new quote straight away with --no-wait', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => readyQuote(['status' => 'NEW', 'quote' => null])])]);

    $this->artisan('flutterwave:fx-quote', ['base' => 'NGN', 'target' => 'USD', 'quantity' => '1617759', '--no-wait' => true])
        ->expectsOutputToContain('flutterwave:fx-status 570e21b4')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

it('fails when the quote fails', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => readyQuote(['status' => 'FAILED', 'quote' => null, 'complete_message' => 'Minimum amount is NGN 1517759.30.'])])]);

    $this->artisan('flutterwave:fx-quote', ['base' => 'NGN', 'target' => 'USD', 'quantity' => '15000'])
        ->expectsOutputToContain('Minimum amount is NGN 1517759.30.')
        ->assertFailed();
});

it('trades a ready quote after confirming the amounts', function () {
    Http::fake([
        FLW_API.'rfq/570e21b4' => Http::response(['status' => 'success', 'data' => readyQuote()]),
        FLW_API.'trades' => Http::response(['status' => 'success', 'data' => ['id' => '08e27e03', 'status' => 'NEW']]),
    ]);

    $this->artisan('flutterwave:fx-trade', ['quote' => '570e21b4', '--narration' => 'Supplier payment'])
        ->expectsConfirmation("Sell NGN 1,517,759.00 for USD 1,000.20? Trades can't be undone.", 'yes')
        ->expectsOutputToContain('Trade 08e27e03 is NEW.')
        ->expectsOutputToContain('flutterwave:fx-status 08e27e03 --trade')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'trades' && $request->data() === ['quote_id' => '570e21b4', 'narration' => 'Supplier payment']);
});

it('refuses to trade a quote that is not ready', function () {
    Http::fake(['*' => Http::response(['status' => 'success', 'data' => readyQuote(['status' => 'EXPIRED'])])]);

    $this->artisan('flutterwave:fx-trade', ['quote' => '570e21b4', '--narration' => 'Supplier payment', '--force' => true])
        ->expectsOutputToContain("This quote is EXPIRED, so it can't be traded.")
        ->assertFailed();

    Http::assertSentCount(1);
});

it('shows fx quote and trade status', function () {
    Http::fake([
        FLW_API.'rfq/570e21b4' => Http::response(['status' => 'success', 'data' => readyQuote()]),
        FLW_API.'trade/08e27e03' => Http::response(['status' => 'success', 'data' => [
            'id' => '08e27e03', 'quantity' => 10000000, 'price' => 629.705485, 'instrument' => 'NGN/USD', 'status' => 'SETTLED',
            'response_message' => 'Transfer was successful', 'recipient' => ['id' => '300408963', 'name' => 'Uzoma Kenkwo'], 'narration' => 'sample exchange',
        ]]),
    ]);

    $this->artisan('flutterwave:fx-status', ['id' => '570e21b4'])->expectsOutputToContain('0.000659')->assertSuccessful();

    $this->artisan('flutterwave:fx-status', ['id' => '08e27e03', '--trade' => true])
        ->expectsOutputToContain('SETTLED')
        ->expectsOutputToContain('USD 629.71')
        ->expectsOutputToContain('Uzoma Kenkwo (300408963)')
        ->assertSuccessful();
});

it('prints the api error for an unknown trade', function () {
    Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid trade id.', 'data' => null], 400)]);

    $this->artisan('flutterwave:fx-status', ['id' => 'nope', '--trade' => true])
        ->expectsOutputToContain('Invalid trade id.')
        ->assertFailed();
});
