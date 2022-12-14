<?php

declare(strict_types=1);

use Flutterwave\Payments\Helpers\Modal;
use Flutterwave\Payments\Services\Transactions;
use Flutterwave\Payments\Services\Webhooks;

return [
    'transactions' => Transactions::class,
    'webhooks' => Webhooks::class,
    'modal' => Modal::class,
];
