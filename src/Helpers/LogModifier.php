<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Helpers;

use Psr\Log\LoggerInterface;

final class LogModifier
{
    public function __invoke(LoggerInterface $logger, string $type, string $message, string $author): void
    {
        $logger->$type("{$author}::{$message}");
    }
}
