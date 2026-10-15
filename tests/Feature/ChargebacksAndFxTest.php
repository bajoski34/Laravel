<?php

use Flutterwave\Payments\Events\ChargebackUpdated;
use Flutterwave\Payments\Events\QuoteCompleted;
use Flutterwave\Payments\Events\WebhookReceived;
use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function proofFile(string $name = 'proof.pdf', int $bytes = 2048): string
{
    $directory = sys_get_temp_dir().'/'.uniqid('flw-proof-', true);
    mkdir($directory);
    file_put_contents($directory.'/'.$name, str_repeat('%', $bytes));

    return $directory.'/'.$name;
}

// Laravel uses the first matching fake, so tests with specific responses don't call this.
function fakeOk(): void
{
    Http::fake([FLW_API.'*' => Http::response(['status' => 'success', 'message' => 'ok', 'data' => []])]);
}

it('lists, finds, accepts and declines chargebacks', function () {
    fakeOk();
    $chargebacks = Flutterwave::chargebacks();
    $chargebacks->all(['status' => 'initiated', 'from' => '2026-10-01', 'currency' => 'NGN']);
    $chargebacks->find('FLW-MOCK-3cc9');
    $chargebacks->accept(1603, 'Customer never received the order.');
    $chargebacks->decline(1599, 'Delivered on 3 October, signed for.', 'https://cdn.filestackcontent.com/UQbC');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks?status=initiated&from=2026-10-01&currency=NGN');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks?flw_ref=FLW-MOCK-3cc9');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === FLW_API.'chargebacks/1603'
        && $request->data() === ['action' => 'accept', 'comment' => 'Customer never received the order.']);
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->url() === FLW_API.'chargebacks/1599'
        && $request->data() === ['action' => 'decline', 'comment' => 'Delivered on 3 October, signed for.', 'prooflink' => 'https://cdn.filestackcontent.com/UQbC']);
});

it('uploads proof as multipart form data', function () {
    fakeOk();
    $path = proofFile('delivery-note.pdf');

    Flutterwave::chargebacks()->uploadProof($path);

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'upload_image'
        && $request->isMultipart()
        && $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-secret')
        && $request[0]['name'] === 'image'
        && $request[0]['filename'] === 'delivery-note.pdf');

    unlink($path);
});

it('uses the original name of a form upload', function () {
    fakeOk();
    $path = proofFile('php5Yh2kL', 100);
    $upload = new UploadedFile($path, 'receipt.png', 'image/png', null, true);

    Flutterwave::chargebacks()->uploadProof($upload);

    Http::assertSent(fn (Request $request) => $request->isMultipart() && $request[0]['filename'] === 'receipt.png');
    unlink($path);
});

it('uploads the proof and declines with its link in one call', function () {
    Http::fake([
        FLW_API.'upload_image' => Http::response(['status' => 'success', 'message' => 'Image sucessfully uploaded', 'data' => 'https://cdn.filestackcontent.com/UQbC']),
        FLW_API.'chargebacks/1599' => Http::response(['status' => 'success', 'message' => 'Chargeback declined', 'data' => ['id' => 1599, 'status' => 'declined']]),
    ]);
    $path = proofFile();

    $response = Flutterwave::chargebacks()->declineWithProof(1599, 'Delivered and signed for.', $path);

    expect($response['message'])->toBe('Chargeback declined');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'chargebacks/1599' && $request['prooflink'] === 'https://cdn.filestackcontent.com/UQbC');
    unlink($path);
});

it('does not decline when the proof upload fails', function () {
    Http::fake([FLW_API.'upload_image' => Http::response(['status' => 'error', 'message' => 'Image is not in required format', 'data' => null], 400)]);
    $path = proofFile();

    expect(fn () => Flutterwave::chargebacks()->declineWithProof(1599, 'Delivered.', $path))
        ->toThrow(FlutterwaveException::class, 'Image is not in required format');

    Http::assertSentCount(1);
    unlink($path);
});

// Datasets hold plain data, not closures: Pest 1 and 2 call closures in a dataset while building it.
// '@name' or '@name:bytes' arguments become proof files, created when the test runs.
function proofArguments(array $arguments): array
{
    return array_map(static function ($argument) {
        if (! is_string($argument) || ! str_starts_with($argument, '@')) {
            return $argument;
        }

        [$name, $bytes] = array_pad(explode(':', substr($argument, 1)), 2, 2048);

        return proofFile($name, (int) $bytes);
    }, $arguments);
}

it('checks chargeback responses before calling the api', function (string $method, array $arguments, string $message) {
    Http::fake();

    expect(fn () => Flutterwave::chargebacks()->{$method}(...proofArguments($arguments)))->toThrow(InvalidArgument::class, $message);
    Http::assertNothingSent();
})->with([
    'empty comment' => ['accept', [1, '  '], 'needs a comment'],
    'decline without proof' => ['decline', [1, 'Delivered.', ''], 'needs a proof link'],
    'missing file' => ['uploadProof', ['/nope/proof.pdf'], "doesn't exist"],
    'wrong file type' => ['uploadProof', ['@proof.docx'], 'JPEG, PNG or PDF'],
    'empty file' => ['uploadProof', ['@blank.pdf:0'], 'is empty'],
    'file too large' => ['uploadProof', ['@scan.jpg:8388608'], 'smaller than 8MB'],
    'decline with proof but no comment' => ['declineWithProof', [1, '', '@proof.pdf'], 'needs a comment'],
]);

it('requests quotes and trades them', function () {
    fakeOk();
    $fx = Flutterwave::fx();
    $fx->quote('ngn', 'usd', 2000000, 'FX-ORDER-7');
    $fx->findQuote('570e21b4');
    $fx->trade('570e21b4', 'Supplier payment', ['investment_code' => 'INV-1']);
    $fx->findTrade('08e27e03');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'rfq'
        && $request->data() === ['base_currency' => 'NGN', 'target_currency' => 'USD', 'quantity' => 2000000, 'reference' => 'FX-ORDER-7']);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === FLW_API.'rfq/570e21b4');
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'trades'
        && $request->data() === ['quote_id' => '570e21b4', 'narration' => 'Supplier payment', 'meta' => ['investment_code' => 'INV-1']]);
    Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === FLW_API.'trade/08e27e03');
});

it('leaves out the optional quote reference and trade meta', function () {
    fakeOk();
    Flutterwave::fx()->quote('GHS', 'USD', 15000);
    Flutterwave::fx()->trade('q1', 'Treasury');

    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'rfq' && ! isset($request['reference']));
    Http::assertSent(fn (Request $request) => $request->url() === FLW_API.'trades' && $request->data() === ['quote_id' => 'q1', 'narration' => 'Treasury']);
});

it('only trades a quote that is still ready', function () {
    Http::fake([
        FLW_API.'rfq/ready' => Http::response(['status' => 'success', 'data' => ['id' => 'ready', 'status' => 'READY']]),
        FLW_API.'rfq/old' => Http::response(['status' => 'success', 'data' => ['id' => 'old', 'status' => 'EXPIRED', 'complete_message' => 'Your RFQ is expired']]),
        FLW_API.'trades' => Http::response(['status' => 'success', 'message' => 'Trade initiated successfully.', 'data' => ['status' => 'NEW']]),
    ]);

    expect(Flutterwave::fx()->tradeIfReady('ready', 'Supplier payment')['message'])->toBe('Trade initiated successfully.');
    expect(fn () => Flutterwave::fx()->tradeIfReady('old', 'Supplier payment'))->toThrow(InvalidArgument::class, 'Quote old is EXPIRED, not READY. Your RFQ is expired');

    Http::assertSentCount(3);
});

it('checks fx requests before calling the api', function (string $method, array $arguments, string $message) {
    Http::fake();

    expect(fn () => Flutterwave::fx()->{$method}(...$arguments))->toThrow(InvalidArgument::class, $message);
    Http::assertNothingSent();
})->with([
    'zero quantity' => ['quote', ['NGN', 'USD', 0], 'greater than zero'],
    'same currency' => ['quote', ['usd', 'USD', 5000], 'two different currencies'],
    'empty narration' => ['trade', ['q1', ' '], 'needs a narration'],
]);

it('turns quote and chargeback webhooks into events', function () {
    Event::fake();
    $headers = ['verif-hash' => 'my-secret-hash'];

    $this->postJson('/flutterwave/webhook', ['event' => 'quote.completed', 'event.type' => 'QUOTE', 'data' => ['id' => 'c085', 'status' => 'READY']], $headers)->assertOk();
    $this->postJson('/flutterwave/webhook', ['event' => 'chargeback.initiated', 'data' => ['id' => 1189808, 'status' => 'initiated', 'due_date' => '2026-10-20T15:59:59.000Z']], $headers)->assertOk();
    $this->postJson('/flutterwave/webhook', ['event' => 'chargeback.lost', 'data' => ['id' => 1189808]], $headers)->assertOk();

    Event::assertDispatched(QuoteCompleted::class, fn (QuoteCompleted $event) => $event->isReady() && $event->quoteId() === 'c085');
    Event::assertDispatched(ChargebackUpdated::class, fn (ChargebackUpdated $event) => $event->needsResponse() && $event->status() === 'initiated');
    Event::assertDispatched(ChargebackUpdated::class, fn (ChargebackUpdated $event) => ! $event->needsResponse() && $event->status() === 'lost');
    Event::assertDispatchedTimes(WebhookReceived::class, 3);
});
