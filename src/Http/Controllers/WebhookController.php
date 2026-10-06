<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Events\ChargeCompleted;
use Flutterwave\Payments\Events\SubscriptionCancelled;
use Flutterwave\Payments\Events\TransferCompleted;
use Flutterwave\Payments\Events\WebhookReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Receives Flutterwave webhooks (signature already checked by middleware)
 * and turns them into Laravel events.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? $payload['event.type'] ?? 'unknown');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        Log::channel('flutterwave')->info("Flutterwave Webhook::Received {$event}");

        if ($event === 'charge.completed') {
            $transaction = $data;
            $verified = false;

            if (config('flutterwave.webhook.verify_charges', true) && isset($data['id'])) {
                $response = app('flutterwave')->verifyTransaction($data['id']);

                if (($response['status'] ?? null) !== 'success' || ! isset($response['data'])) {
                    Log::channel('flutterwave')->error("Flutterwave Webhook::Could not verify transaction {$data['id']}", $response);

                    // A non-2xx response makes Flutterwave retry the webhook later.
                    return response()->json(['status' => 'error', 'message' => 'Transaction verification failed.'], 500);
                }

                $transaction = $response['data'];
                $verified = true;
            }

            WebhookReceived::dispatch($event, $data, $payload);
            ChargeCompleted::dispatch($data, $transaction, $verified);
        } else {
            WebhookReceived::dispatch($event, $data, $payload);

            if ($event === 'transfer.completed') {
                TransferCompleted::dispatch($data);
            } elseif ($event === 'subscription.cancelled') {
                SubscriptionCancelled::dispatch($data);
            }
        }

        return response()->json(['status' => 'success']);
    }
}
