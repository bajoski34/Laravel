<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Controllers;

use Flutterwave\Payments\Jobs\HandleFlutterwaveWebhook;
use Flutterwave\Payments\Webhooks\WebhookProcessor;
use Flutterwave\Payments\Webhooks\WebhookVerificationFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Receives Flutterwave webhooks (signature already checked by middleware)
 * and turns them into Laravel events, once per webhook.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request, WebhookProcessor $processor): JsonResponse
    {
        $payload = $request->json()->all();

        if ($queue = config('flutterwave.webhook.queue')) {
            HandleFlutterwaveWebhook::dispatch($payload)
                ->onConnection(config('flutterwave.webhook.queue_connection'))
                ->onQueue($queue);

            return response()->json(['status' => 'success', 'message' => 'Queued.']);
        }

        try {
            $result = $processor->handle($payload);
        } catch (WebhookVerificationFailed) {
            // A non-2xx response makes Flutterwave retry the webhook later.
            return response()->json(['status' => 'error', 'message' => 'Transaction verification failed.'], 500);
        }

        return match ($result) {
            // Another copy is being handled right now. If that one fails, Flutterwave's retry of this one still counts.
            WebhookProcessor::BUSY => response()->json(['status' => 'error', 'message' => 'This webhook is already being handled.'], 409),
            WebhookProcessor::DUPLICATE => response()->json(['status' => 'success', 'message' => 'Already handled.']),
            default => response()->json(['status' => 'success']),
        };
    }
}
