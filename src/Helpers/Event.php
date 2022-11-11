<?php

namespace Flutterwave\Payments\Helpers;

use Illuminate\Support\Facades\Log;

trait Event
{
    private static string $name = "Flutterwave Event";
    public static function onPaymentSuccess($payload)
    {
        Log::info('Payment Success', $payload);
    }

    public static function onPaymentFailed($payload)
    {
        Log::info('Payment Failed', $payload);
    }

    public static function onPaymentPending($payload)
    {
        $name = self::$name;
        Log::info("$name::Payment Pending", $payload);
    }

    public static function onPaymentCancelled(string $tx_ref): \Illuminate\Http\RedirectResponse
    {
        $name = self::$name;
        Log::info("$name::Payment with tx_ref '$tx_ref' Cancelled");
        return redirect()->route('flutterwave.cancel', ['message' => 'Payment cancelled']);

    }

    public static function onPaymentRefunded($payload)
    {
        Log::info('Payment Refunded', $payload);
    }
}
