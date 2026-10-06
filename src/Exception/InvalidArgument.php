<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Exception;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException as CoreInvalidArgumentException;

final class InvalidArgument extends CoreInvalidArgumentException
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
