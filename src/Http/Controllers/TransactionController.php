<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Backs the transactions components: refunds and webhook resends. Registered by
 * Route::flutterwaveAdmin(); every action needs the `flutterwave.manage` ability.
 */
class TransactionController extends Controller
{
    public function refund(Request $request, string $transaction): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);
        $amount = $validated['amount'] ?? null;
        $context = ['transaction_id' => $transaction, 'amount' => $amount ?? 'full'];

        $response = $this->call(fn () => app('flutterwave')->refund($transaction, $amount, array_filter(['comments' => $validated['comment'] ?? null])));

        if (($response['status'] ?? null) !== 'success') {
            Audit::record('transaction.refund', [...$context, 'error' => $response['message'] ?? null], false);

            return back()->withErrors(['flutterwave' => $response['message'] ?? 'The refund could not be started.']);
        }

        Audit::record('transaction.refund', [...$context, 'refund_id' => $response['data']['id'] ?? null]);

        return back()->with('flutterwave.status', $amount === null
            ? "Transaction {$transaction} is being refunded in full."
            : "A refund of {$amount} for transaction {$transaction} has started.");
    }

    public function resendWebhook(string $transaction): RedirectResponse
    {
        Gate::authorize('flutterwave.manage');

        $response = $this->call(fn () => app('flutterwave')->transactions()->resendFailedHooks($transaction));
        $sent = ($response['status'] ?? null) === 'success';

        Audit::record('transaction.resend_webhook', ['transaction_id' => $transaction, 'error' => $sent ? null : ($response['message'] ?? null)], $sent);

        return $sent
            ? back()->with('flutterwave.status', "The webhook for transaction {$transaction} was sent again.")
            : back()->withErrors(['flutterwave' => $response['message'] ?? 'The webhook could not be resent.']);
    }

    private function call(callable $call): array
    {
        try {
            return $call();
        } catch (FlutterwaveException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
