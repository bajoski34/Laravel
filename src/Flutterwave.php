<?php

declare(strict_types=1);

namespace Flutterwave\Payments;

use Exception;
use Flutterwave\Payments\Data\Api;
use Flutterwave\Payments\Exception\InvalidArgument;
use Flutterwave\Payments\Exception\ServiceNotFound;
use Flutterwave\Payments\Helpers\Event;
use Flutterwave\Payments\Services\Balances;
use Flutterwave\Payments\Services\Banks;
use Flutterwave\Payments\Services\Beneficiaries;
use Flutterwave\Payments\Services\Bills;
use Flutterwave\Payments\Services\Bvn;
use Flutterwave\Payments\Services\Cards;
use Flutterwave\Payments\Services\Chargebacks;
use Flutterwave\Payments\Services\Fx;
use Flutterwave\Payments\Services\Modal;
use Flutterwave\Payments\Services\PaymentPlans;
use Flutterwave\Payments\Services\PayoutSubaccounts;
use Flutterwave\Payments\Services\Preauthorizations;
use Flutterwave\Payments\Services\Refunds;
use Flutterwave\Payments\Services\Settlements;
use Flutterwave\Payments\Services\Stablecoins;
use Flutterwave\Payments\Services\Subaccounts;
use Flutterwave\Payments\Services\Subscriptions;
use Flutterwave\Payments\Services\Transactions;
use Flutterwave\Payments\Services\Transfers;
use Flutterwave\Payments\Services\VirtualAccounts;
use Flutterwave\Payments\Services\Webhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class Flutterwave
{
    use Event;

    const VERSION = '2.3.1';

    /**
     * Services available out of the box. Entries in config('flutterwave.services')
     * are merged on top, so older published config files keep working.
     */
    public const DEFAULT_SERVICES = [
        'transactions' => Transactions::class,
        'webhooks' => Webhooks::class,
        'modals' => Modal::class,
        'transfers' => Transfers::class,
        'banks' => Banks::class,
        'subaccounts' => Subaccounts::class,
        'plans' => PaymentPlans::class,
        'subscriptions' => Subscriptions::class,
        'virtual_accounts' => VirtualAccounts::class,
        'beneficiaries' => Beneficiaries::class,
        'balances' => Balances::class,
        'refunds' => Refunds::class,
        'settlements' => Settlements::class,
        'stablecoins' => Stablecoins::class,
        'cards' => Cards::class,
        'chargebacks' => Chargebacks::class,
        'fx' => Fx::class,
        'preauth' => Preauthorizations::class,
        'bills' => Bills::class,
        'payout_subaccounts' => PayoutSubaccounts::class,
        'bvn' => Bvn::class,
    ];

    private array $config;

    private Api $api;

    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = Log::channel('flutterwave');
        $this->loadConfig();
        $this->api = new Api;
    }

    /**
     * Start a hosted checkout and get the payment link.
     *
     * Flutterwave::checkout(['amount' => 5000, 'email' => 'jane@example.com']);
     *
     * @throws Exception
     */
    public function checkout(array $data): string
    {
        return $this->render('standard', $data);
    }

    /**
     * Start a hosted checkout and redirect the customer to it.
     *
     * Inertia sends forms as XHR requests, which can't follow a redirect to
     * another domain. For those requests this returns the 409 + X-Inertia-Location
     * response that Inertia::location() would, so the browser does a full page visit.
     *
     * @throws Exception
     */
    public function redirect(array $data): RedirectResponse|Response
    {
        $link = $this->checkout($data);

        if (request()->header('X-Inertia')) {
            return new Response('', Response::HTTP_CONFLICT, ['X-Inertia-Location' => $link]);
        }

        return redirect()->away($link);
    }

    /**
     * The inline checkout config as an array, e.g. to pass as an Inertia prop
     * to FlutterwaveCheckout() on the frontend.
     *
     * @throws Exception
     */
    public function inlineConfig(array $data): array
    {
        return json_decode($this->render('inline', $data), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws Exception
     */
    public function render(string $type, array $data): string
    {
        if (empty($this->config['services']['modals'])) {
            $this->logger->notice("Flutterwave::{$type} service is not enabled");
            throw new ServiceNotFound("{$type} service is not enabled");
        }

        if ($type !== 'inline' && $type !== 'standard') {
            $this->logger->notice("Flutterwave::please specify a valid type for the render method. Valid types are 'inline' and 'standard'");
            throw new InvalidArgument("please specify a valid type for the render method. Valid types are 'inline' and 'standard'");
        }

        return $this->use('modals')->render($data, $type);
    }

    /**
     * @throws Exception
     */
    public function use(string $service): object
    {
        $services = $this->config['services'];
        if (empty($services[$service])) {
            $this->logger->error("Flutterwave::{$service} service not found");
            throw new ServiceNotFound("{$service} service not found");
        }

        return new $services[$service]($this->api, $this->config);
    }

    public function transactions(): Transactions
    {
        return $this->use('transactions');
    }

    public function transfers(): Transfers
    {
        return $this->use('transfers');
    }

    public function banks(): Banks
    {
        return $this->use('banks');
    }

    public function subaccounts(): Subaccounts
    {
        return $this->use('subaccounts');
    }

    public function plans(): PaymentPlans
    {
        return $this->use('plans');
    }

    public function subscriptions(): Subscriptions
    {
        return $this->use('subscriptions');
    }

    public function virtualAccounts(): VirtualAccounts
    {
        return $this->use('virtual_accounts');
    }

    public function beneficiaries(): Beneficiaries
    {
        return $this->use('beneficiaries');
    }

    public function balances(): Balances
    {
        return $this->use('balances');
    }

    public function refunds(): Refunds
    {
        return $this->use('refunds');
    }

    public function settlements(): Settlements
    {
        return $this->use('settlements');
    }

    public function stablecoins(): Stablecoins
    {
        return $this->use('stablecoins');
    }

    public function cards(): Cards
    {
        return $this->use('cards');
    }

    public function chargebacks(): Chargebacks
    {
        return $this->use('chargebacks');
    }

    public function fx(): Fx
    {
        return $this->use('fx');
    }

    public function preauth(): Preauthorizations
    {
        return $this->use('preauth');
    }

    public function bills(): Bills
    {
        return $this->use('bills');
    }

    public function payoutSubaccounts(): PayoutSubaccounts
    {
        return $this->use('payout_subaccounts');
    }

    public function bvn(): Bvn
    {
        return $this->use('bvn');
    }

    public function webhooks(): Webhooks
    {
        return $this->use('webhooks');
    }

    public function generateTransactionReference(): string
    {
        return Transactions::generateTransactionReference((string) $this->config['prefix']);
    }

    /**
     * @throws Exception
     */
    public function verifyTransaction(int|string $transactionId): array
    {
        return $this->transactions()->verify($transactionId);
    }

    /**
     * @throws Exception
     */
    public function verifyTransactionReference(string $transactionId): array
    {
        return $this->transactions()->verifyTransactionReference($transactionId);
    }

    /**
     * Verify a transaction and confirm it was paid in full.
     *
     * Returns true only when Flutterwave reports the transaction as successful
     * and, when given, the amount, currency and tx_ref match what you expected.
     */
    public function isSuccessful(int|string $transactionId, int|float|null $expectedAmount = null, ?string $expectedCurrency = null, ?string $expectedReference = null): bool
    {
        $response = $this->verifyTransaction($transactionId);
        $data = $response['data'] ?? [];

        if (($response['status'] ?? null) !== 'success' || ($data['status'] ?? null) !== Data\Status::SUCCESSFUL) {
            return false;
        }

        if ($expectedAmount !== null && (float) ($data['amount'] ?? 0) < (float) $expectedAmount) {
            return false;
        }

        if ($expectedCurrency !== null && strtoupper((string) ($data['currency'] ?? '')) !== strtoupper($expectedCurrency)) {
            return false;
        }

        if ($expectedReference !== null && ($data['tx_ref'] ?? null) !== $expectedReference) {
            return false;
        }

        return true;
    }

    /**
     * Refund a transaction in full, or partially when an amount is given.
     */
    public function refund(int|string $transactionId, int|float|string|null $amount = null, array $options = []): array
    {
        return $this->transactions()->refund($transactionId, $amount, $options);
    }

    public function config(?string $key = null): mixed
    {
        return $key === null ? $this->config : ($this->config[$key] ?? null);
    }

    private function loadConfig(): void
    {
        $this->config = [
            'public_key' => config('flutterwave.publicKey'),
            'secret_key' => config('flutterwave.secretKey'),
            'redirect_url' => config('flutterwave.redirectUrl'),
            'title' => config('flutterwave.title'),
            'description' => config('flutterwave.description'),
            'logo' => config('flutterwave.logo'),
            'country' => config('flutterwave.country'),
            'currency' => config('flutterwave.currency'),
            'payment_options' => config('flutterwave.paymentType', []),
            'prefix' => config('flutterwave.transactionPrefix', 'LARAVEL-'),
            'env' => config('flutterwave.env'),
            'secret_hash' => config('flutterwave.secretHash'),
            'checksum' => (bool) config('flutterwave.checksum', false),
            'encryption_key' => config('flutterwave.encryptionKey'),
            'business_name' => config('flutterwave.businessName'),
            'merchant_id' => config('flutterwave.merchantId'),
            'success_url' => config('flutterwave.successUrl'),
            'cancel_url' => config('flutterwave.cancelUrl'),
            'timeout' => config('flutterwave.timeout', 60),
            'retries' => config('flutterwave.retries', 2),
            'services' => array_merge(self::DEFAULT_SERVICES, (array) config('flutterwave.services', [])),
        ];
    }
}
