<?php

declare(strict_types=1);

namespace Flutterwave\Payments\View\Components;

use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * <x-flutterwave-button amount="5000" email="jane@example.com" />
 */
class PayButton extends Component
{
    public string $buttonId;

    public function __construct(
        public int|float|string $amount,
        public ?string $email = null,
        public ?string $currency = null,
        public ?string $name = null,
        public ?string $phone = null,
        public ?string $txRef = null,
        public ?string $redirectUrl = null,
        public ?string $paymentOptions = null,
        public ?string $paymentPlan = null,
        public array $meta = [],
        public array $customer = [],
        public array $customizations = [],
        public array $subaccounts = [],
        public string $label = 'Pay Now',
    ) {
        $this->buttonId = 'flw-pay-'.Str::lower(Str::random(10));
    }

    public function checkoutConfig(): string
    {
        $customer = array_filter(array_merge([
            'email' => $this->email,
            'name' => $this->name,
            'phone_number' => $this->phone,
        ], $this->customer));

        $payload = array_filter([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tx_ref' => $this->txRef,
            'redirect_url' => $this->redirectUrl,
            'payment_plan' => $this->paymentPlan,
            'customer' => $customer,
            'meta' => $this->meta,
            'customizations' => $this->customizations,
            'subaccounts' => $this->subaccounts,
        ], static fn ($value) => $value !== null && $value !== []);

        $config = json_decode(app('flutterwave')->render('inline', $payload), true);

        if ($this->paymentOptions !== null) {
            $config['payment_options'] = $this->paymentOptions;
        }

        return json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    public function render()
    {
        return view('flutterwave::components.button');
    }
}
