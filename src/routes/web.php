<?php

/*
|--------------------------------------------------------------------------
| Flutterwave example routes
|--------------------------------------------------------------------------
| Published with: php artisan vendor:publish --tag=flutterwave-routes
| Edit freely. The webhook route is registered by the package itself
| (see config/flutterwave.php > webhook), so it is not defined here.
*/

use Flutterwave\Payments\Data\Status;
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Http\ConfirmRequest;
use Flutterwave\Payments\Http\PaymentRequest;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::post('/flutterwave/payment/checkout', function (PaymentRequest $request) {
        $validated = $request->validated();

        $payload = [
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'email' => $validated['email'],
        ];

        if ($request->has('meta')) {
            $payload['meta'] = $request->input('meta');
        }

        $payment_details = Flutterwave::render('inline', $payload);

        return view('flutterwave::modal', compact('payment_details'));
    })->name('flutterwave.checkout');

    Route::get('/flutterwave/payment/callback', function (ConfirmRequest $request) {
        $validated = $request->validated();

        if ($validated['status'] === 'cancelled') {
            return redirect()->route('flutterwave.cancelled');
        }

        // Always re-verify on the server; never trust the status in the query string.
        $response = empty($validated['transaction_id'])
            ? Flutterwave::verifyTransactionReference($validated['tx_ref'])
            : Flutterwave::verifyTransaction($validated['transaction_id']);

        // TODO: also compare $response['data']['amount'] and ['currency'] with your order.
        return match ($response['data']['status'] ?? null) {
            Status::SUCCESSFUL => redirect()->route('flutterwave.successful'),
            Status::PENDING => redirect()->route('flutterwave.pending'),
            default => redirect()->route('flutterwave.failed'),
        };
    })->name('flutterwave.callback');

    Route::get('/flutterwave/payment/success', fn () => view('flutterwave::pages.success'))->name('flutterwave.successful');
    Route::get('/flutterwave/payment/pending', fn () => 'Payment Pending. We will confirm it shortly.')->name('flutterwave.pending');
    Route::get('/flutterwave/payment/failed', fn () => 'Payment Failed')->name('flutterwave.failed');
    Route::get('/flutterwave/payment/cancel', fn () => view('flutterwave::pages.cancel'))->name('flutterwave.cancelled');

    Route::get('/flw-error', function () {
        if (app()->isProduction()) {
            return 'An error occurred. Please try again.';
        }

        return view('flutterwave::errors.invalid');
    })->name('flutterwave.error');
});
