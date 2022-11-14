<?php

namespace Flutterwave\Payments\Exception;

use InvalidArgumentException as CoreInvalidArgumentException;

final class InvalidArgumentException extends CoreInvalidArgumentException
{
    public function render() {
        return redirect()->route('flutterwave.error',[ "message" => $this->message]);
    }
}
