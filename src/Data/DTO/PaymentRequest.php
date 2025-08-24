<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Data\DTO;

final class PaymentRequest
{
    public string $tx_ref;
    public float $amount;
    public string $currency;
    public Customer $customer;
    public ?string $redirect_url;
    public ?array $meta;
    public ?array $customizations;

    public function __construct(
        string $tx_ref,
        float $amount,
        string $currency,
        Customer $customer,
        ?string $redirect_url = null,
        ?array $meta = null,
        ?array $customizations = null
    ) {
        $this->tx_ref = $tx_ref;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->customer = $customer;
        $this->redirect_url = $redirect_url;
        $this->meta = $meta;
        $this->customizations = $customizations;
    }

    public function toArray(): array
    {
        $data = [
            'tx_ref' => $this->tx_ref,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer' => $this->customer->toArray(),
        ];

        if ($this->redirect_url !== null) {
            $data['redirect_url'] = $this->redirect_url;
        }

        if ($this->meta !== null && !empty($this->meta)) {
            $data['meta'] = $this->meta;
        }

        if ($this->customizations !== null && !empty($this->customizations)) {
            $data['customizations'] = $this->customizations;
        }

        return $data;
    }

    public static function make(
        string $tx_ref,
        float $amount,
        string $currency,
        Customer $customer,
        ?string $redirect_url = null
    ): self {
        return new self($tx_ref, $amount, $currency, $customer, $redirect_url);
    }

    public function withMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function withCustomizations(array $customizations): self
    {
        $this->customizations = $customizations;
        return $this;
    }
}