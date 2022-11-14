<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Exception;

use Exception;

final class NetworkConnection extends Exception
{
    public function render(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('flutterwave.error', [ 'message' => $this->message]);
    }
}
