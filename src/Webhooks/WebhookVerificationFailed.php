<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Webhooks;

use RuntimeException;

/**
 * A charge.completed webhook couldn't be confirmed with the API. Nothing was
 * dispatched; Flutterwave (or the queue) should try again later.
 */
final class WebhookVerificationFailed extends RuntimeException {}
