<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Console\Concerns;

use Flutterwave\Payments\Exception\FlutterwaveException;
use Flutterwave\Payments\Exception\InvalidArgument;

trait HandlesApiErrors
{
    /**
     * Run an API call, printing Flutterwave's or the package's error message instead of a stack trace.
     *
     * @return array|null the response, or null when the call failed
     */
    protected function attempt(callable $call): ?array
    {
        try {
            return $call();
        } catch (FlutterwaveException|InvalidArgument $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    /**
     * Ask before changing something on Flutterwave. Without a terminal to ask in, --force is required.
     */
    protected function confirmChange(string $question): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->warn('Nothing was changed. Pass --force to run this without a prompt.');

            return false;
        }

        if ($this->confirm($question)) {
            return true;
        }

        $this->warn('Nothing was changed.');

        return false;
    }
}
