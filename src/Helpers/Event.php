<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Helpers;

use Illuminate\Support\Facades\Log;

trait Event
{
    private static string $name = 'Flutterwave Event';

    public static function onPaymentSuccess($payload): \Illuminate\Http\RedirectResponse
    {
        $name = self::$name;
        Log::channel('flutterwave')->info("{$name}::Payment with id {$payload['id']} successful.");
        return redirect()->route('flutterwave.success', ['message' => 'Payment successful']);
    }

    public static function onPaymentFailed($tx_ref): \Illuminate\Http\RedirectResponse
    {
        $name = self::$name;
        Log::channel('flutterwave')->info("{$name}::Payment with tx_ref '{$tx_ref}' Cancelled");
        return redirect()->route('flutterwave.cancel', ['message' => 'Payment Failed']);
    }

    public static function onPaymentPending($payload): void
    {
        Log::channel('flutterwave');
        $name = self::$name;
        Log::channel('flutterwave')->info("{$name}::Payment Pending", $payload);
    }

    public static function onPaymentCancelled(string $tx_ref): \Illuminate\Http\RedirectResponse
    {
        $name = self::$name;
        Log::channel('flutterwave')->info("{$name}::Payment with tx_ref '{$tx_ref}' Cancelled");

        return redirect()->route('flutterwave.cancel', ['message' => 'Payment cancelled']);
    }

    public static function onPaymentRefunded($payload): void
    {
        Log::channel('flutterwave')->info('Payment Refunded', $payload);
    }
}
