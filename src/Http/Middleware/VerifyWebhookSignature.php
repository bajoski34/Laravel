<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Http\Middleware;

use Closure;
use Flutterwave\Payments\Services\Webhooks;
use Illuminate\Http\Request;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $valid = app('flutterwave')->webhooks()->isValid(
            $request->getContent(),
            $request->header(Webhooks::SECURE_HEADER),
            $request->header(Webhooks::SIGNATURE_HEADER),
        );

        if (! $valid) {
            return response()->json(['status' => 'error', 'message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
