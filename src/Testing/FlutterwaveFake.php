<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Testing;

use Closure;
use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Facades\Flutterwave;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Flutterwave::fake() for your app's tests: answers every Flutterwave API call
 * with a realistic response, without touching the network or your other HTTP fakes.
 *
 *     $flutterwave = Flutterwave::fake()->transaction(4975363, ['amount' => 5000]);
 *     // ... exercise your app ...
 *     $flutterwave->assertCheckoutCreated(fn (array $payment) => $payment['amount'] === 5000);
 */
class FlutterwaveFake
{
    /** @var array<int, array{0: string, 1: string, 2: array|Closure, 3: int}> */
    private array $stubs = [];

    /** @var array<string, array> */
    private array $transactions = [];

    private int $sequence = 1000;

    public static function start(): self
    {
        $fake = new self;

        // Make sure the package can run without real keys in the test environment.
        config(array_filter([
            'flutterwave.secretKey' => config('flutterwave.secretKey') ?: 'FLWSECK_TEST-fake-secret-X',
            'flutterwave.publicKey' => config('flutterwave.publicKey') ?: 'FLWPUBK_TEST-fake-public-X',
            'flutterwave.secretHash' => config('flutterwave.secretHash') ?: 'fake-secret-hash',
        ]));
        app()->forgetInstance('flutterwave');
        Flutterwave::clearResolvedInstance('flutterwave');

        // Laravel answers with the first matching HTTP fake, so register one stub per test and have
        // it ask the latest FlutterwaveFake. Calling fake() again then starts over cleanly.
        app()->instance(self::class, $fake);

        if (! app()->bound(self::class.'.registered')) {
            Http::fake([parse_url(Api::BASE_URL, PHP_URL_HOST).'/*' => fn (Request $request) => app(self::class)->respondTo($request)]);
            app()->instance(self::class.'.registered', true);
        }

        return $fake;
    }

    /**
     * Make GET transactions/{id}/verify (and verify_by_reference) return this transaction.
     * Unregistered transactions come back as "not found", so a test can't pass by accident.
     */
    public function transaction(int|string $id, array $attributes = []): self
    {
        $amount = $attributes['amount'] ?? 1000;

        $this->transactions[(string) $id] = array_replace_recursive([
            'id' => is_numeric($id) ? (int) $id : $id,
            'tx_ref' => 'FAKE-'.$id,
            'flw_ref' => 'FLW-MOCK-'.md5((string) $id),
            'amount' => $amount,
            'charged_amount' => $amount,
            'app_fee' => 0,
            'currency' => 'NGN',
            'status' => 'successful',
            'payment_type' => 'card',
            'created_at' => gmdate('Y-m-d\TH:i:s.000\Z'),
            'customer' => ['id' => 1, 'name' => 'Jane Doe', 'email' => 'customer@example.com', 'phone_number' => null],
            'card' => ['first_6digits' => '553188', 'last_4digits' => '2950', 'issuer' => 'MASTERCARD', 'country' => 'NG', 'type' => 'MASTERCARD', 'expiry' => '09/32', 'token' => 'flw-t1nf-fake-'.md5((string) $id).'-m03k'],
        ], $attributes);

        return $this;
    }

    /**
     * Answer one endpoint yourself. $path may use * for one path segment, e.g. 'transfers/*'.
     * $response is the JSON body, or a closure that receives the request and returns it.
     */
    public function respond(string $method, string $path, array|Closure $response, int $status = 200): self
    {
        array_unshift($this->stubs, [strtoupper($method), $path, $response, $status]);

        return $this;
    }

    /**
     * Make an endpoint fail the way Flutterwave does, e.g. failing('POST', 'transfers', 'Insufficient balance').
     */
    public function failing(string $method, string $path, string $message, int $status = 400): self
    {
        return $this->respond($method, $path, ['status' => 'error', 'message' => $message, 'data' => null], $status);
    }

    /** Headers that make a webhook request pass the package's signature check. */
    public static function webhookHeaders(): array
    {
        return ['verif-hash' => (string) config('flutterwave.secretHash')];
    }

    // ---------------------------------------------------------------- assertions

    public function assertCheckoutCreated(?Closure $callback = null): self
    {
        return $this->assertSent('POST', 'payments', $callback, 'No hosted checkout was created.');
    }

    public function assertTransferSent(?Closure $callback = null): self
    {
        return $this->assertSent('POST', 'transfers', $callback, 'No transfer was sent.');
    }

    public function assertCardCharged(?Closure $callback = null): self
    {
        return $this->assertSent('POST', 'tokenized-charges', $callback, 'No saved card was charged.');
    }

    public function assertRefunded(int|string $transactionId, int|float|null $amount = null): self
    {
        return $this->assertSent('POST', "transactions/{$transactionId}/refund", fn (array $data) => $amount === null || (float) ($data['amount'] ?? 0) === (float) $amount, "Transaction {$transactionId} was not refunded".($amount === null ? '.' : " for {$amount}."));
    }

    public function assertBillPaid(?Closure $callback = null): self
    {
        return $this->assertSent('POST', 'billers/*/items/*/payment', $callback, 'No bill was paid.');
    }

    /**
     * $callback receives the request body (or query) as an array, and the Request.
     */
    public function assertSent(string $method, string $path, ?Closure $callback = null, ?string $message = null): self
    {
        $matching = array_filter($this->sent($method, $path), fn (array $sent) => $callback === null || $callback($sent['data'], $sent['request']));

        PHPUnit::assertNotEmpty($matching, $message ?? "No {$method} {$path} request was sent to Flutterwave".($callback ? ' that matched the callback.' : '.'));

        return $this;
    }

    public function assertNotSent(string $method, string $path): self
    {
        PHPUnit::assertEmpty($this->sent($method, $path), "An unexpected {$method} {$path} request was sent to Flutterwave.");

        return $this;
    }

    public function assertNothingSent(): self
    {
        PHPUnit::assertEmpty($this->sent(), 'Requests were sent to Flutterwave.');

        return $this;
    }

    /**
     * Requests sent to Flutterwave, optionally only those matching a method and path.
     *
     * @return array<int, array{method: string, path: string, data: array, request: Request}>
     */
    public function sent(?string $method = null, ?string $path = null): array
    {
        $sent = [];

        foreach (Http::recorded() as [$request]) {
            if (! str_starts_with($request->url(), Api::BASE_URL)) {
                continue;
            }

            $requestPath = $this->pathOf($request);

            if (($method === null || strtoupper($method) === $request->method()) && ($path === null || $this->matches($path, $requestPath))) {
                $sent[] = ['method' => $request->method(), 'path' => $requestPath, 'data' => $this->dataOf($request), 'request' => $request];
            }
        }

        return $sent;
    }

    // ---------------------------------------------------------------- responses

    /**
     * @internal Called by the HTTP fake registered in start().
     */
    public function respondTo(Request $request)
    {
        $path = $this->pathOf($request);
        $data = $this->dataOf($request);

        foreach ($this->stubs as [$method, $pattern, $response, $status]) {
            if ($method === $request->method() && $this->matches($pattern, $path)) {
                return Http::response($response instanceof Closure ? $response($request) : $response, $status);
            }
        }

        [$body, $status] = $this->defaultResponse($request->method(), $path, $data);

        return Http::response($body, $status);
    }

    /**
     * @return array{0: array, 1: int}
     */
    private function defaultResponse(string $method, string $path, array $data): array
    {
        $ok = static fn (string $message, mixed $payload, array $extra = []) => [['status' => 'success', 'message' => $message, 'data' => $payload, ...$extra], 200];
        $id = fn () => ++$this->sequence;
        $now = gmdate('Y-m-d\TH:i:s.000\Z');

        return match (true) {
            $method === 'POST' && $path === 'payments' => $ok('Hosted Link', ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/fake-'.substr(md5((string) ($data['tx_ref'] ?? $id())), 0, 12)]),

            $method === 'GET' && $this->matches('transactions/*/verify', $path) => $this->verified($this->transactions[explode('/', $path)[1]] ?? null, explode('/', $path)[1]),
            $method === 'GET' && $path === 'transactions/verify_by_reference' => $this->verified($this->byReference((string) ($data['tx_ref'] ?? '')), (string) ($data['tx_ref'] ?? '')),
            $method === 'POST' && $this->matches('transactions/*/refund', $path) => $ok('Transaction refund initiated', ['id' => $id(), 'tx_id' => (int) explode('/', $path)[1], 'amount_refunded' => $data['amount'] ?? null, 'status' => 'completed', 'created_at' => $now]),
            $method === 'POST' && $this->matches('transactions/*/resend-hook', $path) => $ok('Webhook resent', null),

            $method === 'POST' && $path === 'transfers' && ($data['account_number'] ?? null) === SandboxData::BLACKLISTED_ACCOUNT => [['status' => 'error', 'message' => 'Payout was made to a blacklisted account number', 'data' => null], 400],
            $method === 'POST' && $path === 'transfers' => $ok('Transfer Queued Successfully', ['id' => $id(), 'account_number' => $data['account_number'] ?? null, 'bank_code' => $data['account_bank'] ?? null, 'amount' => $data['amount'] ?? null, 'currency' => $data['currency'] ?? 'NGN', 'reference' => $data['reference'] ?? null, 'status' => 'NEW', 'created_at' => $now]),
            $method === 'GET' && $this->matches('transfers/*', $path) => $ok('Transfer fetched', ['id' => (int) explode('/', $path)[1], 'status' => 'SUCCESSFUL']),

            $method === 'POST' && $path === 'tokenized-charges' => $ok('Charge initiated', ['id' => $id(), 'tx_ref' => $data['tx_ref'] ?? null, 'flw_ref' => 'FLW-MOCK-'.md5((string) $this->sequence), 'amount' => $data['amount'] ?? null, 'charged_amount' => $data['amount'] ?? null, 'currency' => $data['currency'] ?? 'NGN', 'status' => ! empty($data['preauthorize']) ? 'pending' : 'successful', 'charge_type' => ! empty($data['preauthorize']) ? 'preauth' : 'normal', 'payment_type' => 'card', 'created_at' => $now, 'customer' => ['email' => $data['email'] ?? null]]),
            $method === 'POST' && $this->matches('charges/*/capture', $path) => $ok('Charge captured', ['flw_ref' => explode('/', $path)[1], 'amount' => $data['amount'] ?? null, 'status' => 'successful', 'charge_type' => 'preauth']),
            $method === 'POST' && $this->matches('charges/*/void', $path) => $ok('Charge voided', ['flw_ref' => explode('/', $path)[1], 'status' => 'successful', 'processor_response' => 'Charge voided']),
            $method === 'POST' && $this->matches('charges/*/refund', $path) => $ok('Charge refunded', ['flw_ref' => explode('/', $path)[1], 'amount' => $data['amount'] ?? null, 'status' => 'successful']),

            $method === 'POST' && $path === 'virtual-account-numbers' => $ok('Virtual account created', ['response_code' => '02', 'response_message' => 'Transaction in progress', 'flw_ref' => 'FAKE'.$id(), 'order_ref' => 'URF_FAKE_'.$this->sequence, 'account_number' => '95'.str_pad((string) $this->sequence, 8, '0', STR_PAD_LEFT), 'bank_name' => 'Flutterwave MFB', 'created_at' => gmdate('Y-m-d H:i:s'), 'expiry_date' => ! empty($data['is_permanent']) ? 'N/A' : gmdate('Y-m-d H:i:s', time() + (int) ($data['expires'] ?? 3600)), 'note' => 'Please make a bank transfer to Fake Store FLW', 'amount' => number_format((float) ($data['amount'] ?? 0), 2, '.', '')]),

            $method === 'POST' && $path === 'accounts/resolve' => $ok('Account details fetched', ['account_number' => $data['account_number'] ?? null, 'account_name' => 'Fake Account Name']),
            $method === 'GET' && $this->matches('banks/*', $path) => $ok('Banks fetched successfully', [['id' => 191, 'code' => '044', 'name' => 'Access Bank'], ['id' => 192, 'code' => '058', 'name' => 'Guaranty Trust Bank']]),
            $method === 'GET' && str_starts_with($path, 'balances') => $ok('Wallet balances fetched', [['currency' => 'NGN', 'available_balance' => 250000, 'ledger_balance' => 250000], ['currency' => 'USD', 'available_balance' => 1200, 'ledger_balance' => 1200]]),

            $method === 'POST' && $path === 'rfq' => $ok('Request for quote submitted.', ['id' => 'fake-quote-'.$id(), 'reference' => $data['reference'] ?? 'FAKE-RFQ', 'quantity' => $data['quantity'] ?? null, 'instrument' => ($data['base_currency'] ?? 'NGN').'/'.($data['target_currency'] ?? 'USD'), 'quote' => null, 'status' => 'NEW', 'created_at' => $now]),
            $method === 'GET' && $this->matches('rfq/*', $path) => $ok('Quote fetched successfully.', ['id' => explode('/', $path)[1], 'reference' => 'FAKE-RFQ', 'quantity' => 1617759, 'instrument' => 'NGN/USD', 'quote' => ['best_available_rate' => '0.000659', 'approved_quantity' => 1617759, 'total_value' => 1066.10, 'expiry' => gmdate('Y-m-d\TH:i:s.000\Z', time() + 300)], 'status' => 'READY', 'created_at' => $now, 'complete_message' => 'Your RFQ is ready']),
            $method === 'POST' && $path === 'trades' => $ok('Trade initiated successfully.', ['id' => 'fake-trade-'.$id(), 'quantity' => 1617759, 'approved_quantity' => 1617759, 'price' => 1066.10, 'instrument' => 'NGN/USD', 'status' => 'NEW', 'narration' => $data['narration'] ?? null, 'reference' => 'TRD-FAKE', 'created_at' => $now]),
            $method === 'GET' && $this->matches('trade/*', $path) => $ok('Trade item fetched.', ['id' => explode('/', $path)[1], 'quantity' => 1617759, 'price' => 1066.10, 'instrument' => 'NGN/USD', 'status' => 'SETTLED', 'response_message' => 'Transfer was successful', 'created_at' => $now]),

            $method === 'POST' && $this->matches('billers/*/items/*/payment', $path) => $ok('Bill payment successful', ['phone_number' => $data['customer_id'] ?? null, 'amount' => $data['amount'] ?? null, 'network' => explode('/', $path)[1], 'code' => '200', 'tx_ref' => 'CF-FLYAPI-FAKE'.$id(), 'reference' => $data['reference'] ?? null, 'recharge_token' => null, 'fee' => 0]),
            $method === 'GET' && $this->matches('bill-items/*/validate', $path) => $ok('Item validated successfully', ['response_code' => '00', 'response_message' => 'Successful', 'name' => 'Fake Customer', 'customer' => $data['customer'] ?? null, 'product_code' => explode('/', $path)[1], 'fee' => 0, 'minimum' => 0, 'maximum' => 0]),

            $method === 'POST' && $path === 'payout-subaccounts' => $ok('Payout subaccount created', ['id' => $id(), 'account_reference' => 'PSAFAKE'.$this->sequence, 'account_name' => $data['account_name'] ?? null, 'email' => $data['email'] ?? null, 'country' => $data['country'] ?? 'NG', 'nuban' => '98'.str_pad((string) $this->sequence, 8, '0', STR_PAD_LEFT), 'bank_name' => 'Flutterwave MFB', 'bank_code' => '090567', 'status' => 'ACTIVE', 'created_at' => $now]),

            $method === 'POST' && $path === 'bvn/verifications' => $ok('Bvn verification initiated', ['url' => 'https://nibss-bvn-consent-management.myflutterwave.com/cms/BvnConsent?session=fake', 'reference' => 'FLWFAKE'.str_pad((string) $id(), 18, '0', STR_PAD_LEFT)]),
            $method === 'GET' && $this->matches('bvn/verifications/*', $path) => $ok('Bvn details fetched', ['first_name' => SandboxData::BVN_NEW_CONSENT[1], 'last_name' => SandboxData::BVN_NEW_CONSENT[2], 'status' => 'COMPLETED', 'reference' => explode('/', $path)[2], 'bvn_data' => ['bvn' => SandboxData::BVN_NEW_CONSENT[0], 'firstName' => SandboxData::BVN_NEW_CONSENT[1], 'surname' => SandboxData::BVN_NEW_CONSENT[2]]]),

            default => $ok('Fake response', $method === 'GET' ? [] : null),
        };
    }

    private function verified(?array $transaction, string $id): array
    {
        if ($transaction === null) {
            return [['status' => 'error', 'message' => "No transaction was found for {$id}. Register it in your test with Flutterwave::fake()->transaction({$id}, [...]).", 'data' => null], 400];
        }

        return [['status' => 'success', 'message' => 'Transaction fetched successfully', 'data' => $transaction], 200];
    }

    private function byReference(string $txRef): ?array
    {
        foreach ($this->transactions as $transaction) {
            if (($transaction['tx_ref'] ?? null) === $txRef) {
                return $transaction;
            }
        }

        return null;
    }

    private function pathOf(Request $request): string
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return trim((string) preg_replace('#^/'.preg_quote(Api::LATEST_VERSION, '#').'/#', '', $path), '/');
    }

    private function dataOf(Request $request): array
    {
        if ($request->method() === 'GET') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query;
        }

        return $request->isMultipart() ? ['multipart' => $request->data()] : (array) $request->data();
    }

    private function matches(string $pattern, string $path): bool
    {
        $regex = '#^'.str_replace('\*', '[^/]+', preg_quote(trim($pattern, '/'), '#')).'$#';

        return (bool) preg_match($regex, $path);
    }
}
