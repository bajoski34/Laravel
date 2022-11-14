<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Exception;

use InvalidArgumentException as CoreInvalidArgumentException;

final class InvalidArgument extends CoreInvalidArgumentException
{
    public function report(): void
    {
        #TODO: report to either sentry or error reporting platform
    }
    public function render(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('flutterwave.error', [ 'message' => $this->message]);
    }
}
