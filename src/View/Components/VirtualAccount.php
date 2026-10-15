<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * <x-flutterwave-virtual-account amount="5000" email="jane@example.com" tx-ref="ORDER-42" />
 *
 * Creates a dynamic virtual account and shows the bank details with a copy
 * button and an expiry countdown. Pass a tx-ref so refreshing the page shows
 * the same account instead of creating a new one, or pass an account you
 * already created, ideally with virtualAccounts()->dynamicFor().
 */
class VirtualAccount extends Component
{
    public string $componentId;

    public array $details;

    /** Unix timestamp the account expires at, when it is known. */
    public ?int $expiresAt = null;

    public function __construct(
        public int|float|string|null $amount = null,
        public ?string $email = null,
        public string $currency = 'NGN',
        public ?string $txRef = null,
        public ?int $expires = null,
        public ?string $firstname = null,
        public ?string $lastname = null,
        public ?string $phone = null,
        public ?string $narration = null,
        public ?string $bankCode = null,
        public ?array $account = null,
        public string $expiredMessage = 'This account has expired. Refresh the page to get a new one.',
    ) {
        $this->componentId = 'flw-va-'.Str::lower(Str::random(10));

        $this->details = $account !== null
            ? ($account['data'] ?? $account)
            : app('flutterwave')->virtualAccounts()->dynamicFor((string) $this->email, $this->amount ?? 0, $this->txRef, array_filter([
                'currency' => $this->currency,
                'expires' => $this->expires,
                'firstname' => $this->firstname,
                'lastname' => $this->lastname,
                'phonenumber' => $this->phone,
                'narration' => $this->narration,
                'bank_code' => $this->bankCode,
            ], static fn ($value) => $value !== null));

        // Accounts from dynamicFor() carry expires_at; a raw API response only has Flutterwave's local expiry_date.
        $this->expiresAt = isset($this->details['expires_at']) ? (strtotime((string) $this->details['expires_at']) ?: null) : null;
    }

    public function render()
    {
        return view('flutterwave::components.virtual-account');
    }
}
