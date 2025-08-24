<?php

declare(strict_types=1);

use Flutterwave\Payments\Helpers\Modal;
use Flutterwave\Payments\Services\Cards;
use Flutterwave\Payments\Services\MobileMoney;
use Flutterwave\Payments\Services\Plans;
use Flutterwave\Payments\Services\Subaccounts;
use Flutterwave\Payments\Services\Transactions;
use Flutterwave\Payments\Services\Transfers;
use Flutterwave\Payments\Services\Webhooks;

return [
    'transactions' => Transactions::class,
    'webhooks' => Webhooks::class,
    'modal' => Modal::class,
    'transfers' => Transfers::class,
    'cards' => Cards::class,
    'subaccounts' => Subaccounts::class,
    'plans' => Plans::class,
    'mobilemoney' => MobileMoney::class,
];
