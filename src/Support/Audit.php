<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Support;

use Illuminate\Support\Facades\Log;

/**
 * Records who refunded a customer or moved money through the package's admin screens
 * and commands, in the flutterwave log channel, as Flutterwave's security guidance asks.
 */
final class Audit
{
    public static function record(string $action, array $context = [], bool $succeeded = true): void
    {
        // Web requests have a matched route; artisan commands don't.
        $web = app()->bound('request') && request()->route() !== null;

        Log::channel('flutterwave')->notice("Flutterwave Audit::{$action} ".($succeeded ? 'succeeded' : 'failed'), [
            ...$context,
            'user_id' => auth()->id(),
            'via' => $web ? 'web' : 'console',
            'ip' => $web ? request()->ip() : null,
            'console_user' => $web ? null : get_current_user(),
        ]);
    }
}
