<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string checkout(array $data)
 * @method static \Illuminate\Http\RedirectResponse redirect(array $data)
 * @method static string render(string $type, array $data)
 * @method static object use(string $service)
 * @method static string generateTransactionReference()
 * @method static array verifyTransaction(int|string $transactionId)
 * @method static array verifyTransactionReference(string $txRef)
 * @method static bool isSuccessful(int|string $transactionId, int|float|null $expectedAmount = null, ?string $expectedCurrency = null)
 * @method static array refund(int|string $transactionId, int|float|string|null $amount = null)
 * @method static mixed config(?string $key = null)
 * @method static \Flutterwave\Payments\Services\Transactions transactions()
 * @method static \Flutterwave\Payments\Services\Transfers transfers()
 * @method static \Flutterwave\Payments\Services\Banks banks()
 * @method static \Flutterwave\Payments\Services\Subaccounts subaccounts()
 * @method static \Flutterwave\Payments\Services\PaymentPlans plans()
 * @method static \Flutterwave\Payments\Services\Subscriptions subscriptions()
 * @method static \Flutterwave\Payments\Services\VirtualAccounts virtualAccounts()
 * @method static \Flutterwave\Payments\Services\Beneficiaries beneficiaries()
 * @method static \Flutterwave\Payments\Services\Balances balances()
 * @method static \Flutterwave\Payments\Services\Refunds refunds()
 * @method static \Flutterwave\Payments\Services\Settlements settlements()
 * @method static \Flutterwave\Payments\Services\Stablecoins stablecoins()
 * @method static \Flutterwave\Payments\Services\Webhooks webhooks()
 *
 * @see \Flutterwave\Payments\Flutterwave
 */
class Flutterwave extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'flutterwave';
    }
}
