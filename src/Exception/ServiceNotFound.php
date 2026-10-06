<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Exception;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

final class ServiceNotFound extends Exception
{
    /**
     * @return RedirectResponse|false
     */
    public function render()
    {
        if (! Route::has('flutterwave.error')) {
            return false;
        }

        return redirect()->route('flutterwave.error', ['message' => $this->message]);
    }
}
