<?php

declare(strict_types=1);

use Flutterwave\Payments\Services\Transactions;
use Flutterwave\Payments\Services\Webhooks;
use Flutterwave\Payments\Helpers\Modal;

return [
    'transactions' => Transactions::class,
    'webhooks' => Webhooks::class,
    'modal' => Modal::class,
];
