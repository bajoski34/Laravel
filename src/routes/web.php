<?php

use Illuminate\Support\Facades\Route;
use Flutterwave\Payments\Facades\Flutterwave;
use Flutterwave\Payments\Data\Status;
use Flutterwave\Payments\Http\ConfirmRequest;
use Flutterwave\Payments\Http\PaymentRequest;

Route::post('/flutterwave/payment/checkout', function (PaymentRequest $request) {
    $validated = $request->validated();
    $amount = $validated['amount'];
    $currency = $validated['currency'];
    $email = $validated['email'];

    $payload = [
        "tx_ref" => Flutterwave::generateTransactionReference(),
        "amount" => $amount,
        "currency" => $currency,
        "customer" => [
            "email" => $email
        ],
    ];

    if($request->has('meta')) {
        $payload['meta'] = $request->get('meta');   
    }

    $payment_details = Flutterwave::render('inline', $payload);

    return view('flutterwave::modal', compact('payment_details'));
})->name('flutterwave.checkout');

Route::get('/flutterwave/payment/callback', function (ConfirmRequest $request) {
    $validated = $request->validated();
    $tx_ref = $validated['tx_ref'];
    $transaction = Flutterwave::use('transactions');

    try {
        [ 'status' => $status, 'data' => $transactionData ] = $transaction->verifyTransactionReference($tx_ref);

        if ($status === 'success') {
            switch ($transactionData['status']) {
                case Status::SUCCESSFUL:
                    return redirect()->route('flutterwave.successful');
                    break;
                case Status::PENDING:
                    //redirect user to a page and poll the verify endpoint with $transaction->verifyTransactionReference($tx_ref);
                    break;
                case Status::FAILED:
                    return redirect()->route('flutterwave.failed');
                    break;
            }
        }
    } catch (\Throwable $th) {
        //throw $th;
    }

})->name('flutterwave.callback');

Route::get('/flutterwave/payment/success', function () {
    return 'Payment Successful';
})->name('flutterwave.successful');

Route::get('/flutterwave/payment/failed', function () {
    return 'Payment Failed';
})->name('flutterwave.failed');

Route::get('/flutterwave/payment/cancel', function () {
    return 'Payment Cancelled';
})->name('flutterwave.cancelled');

Route::post('flutterwave/payment/webhook', function () {
    $method = request()->method();
    if ($method === 'POST') {
        //get the request body
        $body = request()->getContent();
        $webhook = Flutterwave::use('webhooks');
        $transaction = Flutterwave::use('transactions');
        //get the request signature
        $signature = request()->header($webhook::SECURE_HEADER);

        //verify the signature
        $isVerified = $webhook->verifySignature($body, $signature);

        if ($isVerified) {
            ['tx_ref' => $tx_ref, 'id' => $id] = $webhook->getHook();
            ['status' => $status, 'data' => $transactionData] = $transaction->verifyTransactionReference($tx_ref);

            $responseData = ['tx_ref' => $tx_ref, 'id' => $id];
            if ($status === 'success') {
                switch ($transactionData['status']) {
                    case Status::SUCCESSFUL:
                        // do something
                        //save to database
                        //send email
                        break;
                    case Status::PENDING:
                        // do something
                        //save to database
                        //send email
                        break;
                    case Status::FAILED:
                        // do something
                        //save to database
                        //send email
                        break;
                }
            }

            return response()->json(['status' => 'success', 'message' => 'Webhook verified by Flutterwave Laravel Package', 'data' => $responseData]);
        }

        return response()->json(['status' => 'error', 'message' => 'Access denied. Hash invalid'])->setStatusCode(401);
    }

    // return 404
    return abort(404);
})->name('flutterwave.webhook');

Route::get('/flw-error', function () {
    if (app()->isProduction()) {
        return "An error occured. Please try again.";
    }
    return view('flutterwave::errors.invalid');
})->name('flutterwave.error');
