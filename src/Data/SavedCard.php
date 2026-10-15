<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Data;

use DateTimeImmutable;
use DateTimeInterface;
use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * A card saved from a verified payment, ready to charge again with cards()->charge().
 */
final class SavedCard
{
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly ?string $last4 = null,
        public readonly ?string $type = null,
        /** Card expiry as MM/YY. */
        public readonly ?string $expiry = null,
        public readonly ?DateTimeInterface $savedAt = null,
    ) {}

    /**
     * Read the card from a verified transaction, e.g. ChargeCompleted::$transaction or
     * Flutterwave::verifyTransaction($id)['data'].
     */
    public static function fromTransaction(array $transaction): self
    {
        $token = $transaction['card']['token'] ?? null;
        $email = $transaction['customer']['email'] ?? null;

        if (! $token || ! $email) {
            throw new InvalidArgument('This transaction has no card token to save. Only card payments can be saved; check payment_type is card.');
        }

        return new self(
            (string) $token,
            (string) $email,
            isset($transaction['card']['last_4digits']) ? (string) $transaction['card']['last_4digits'] : null,
            $transaction['card']['type'] ?? null,
            $transaction['card']['expiry'] ?? null,
            new DateTimeImmutable,
        );
    }

    /** Flutterwave tokens last one year from when the card was saved. */
    public function tokenExpiresAt(): ?DateTimeInterface
    {
        return $this->savedAt ? DateTimeImmutable::createFromInterface($this->savedAt)->modify('+1 year') : null;
    }

    /** True when the token, or the card itself, runs out within $days. Ask the customer to pay by card again to renew it. */
    public function expiresWithin(int $days = 30): bool
    {
        $limit = (new DateTimeImmutable)->modify("+{$days} days");
        $token = $this->tokenExpiresAt();

        if ($token !== null && $token <= $limit) {
            return true;
        }

        if ($this->expiry && preg_match('#^(\d{2})/(\d{2})$#', $this->expiry, $parts)) {
            $cardEnds = (new DateTimeImmutable("20{$parts[2]}-{$parts[1]}-01"))->modify('last day of this month 23:59:59');

            return $cardEnds <= $limit;
        }

        return false;
    }

    /** "MASTERCARD ending 2950" */
    public function label(): string
    {
        return trim(($this->type ?? 'Card').($this->last4 ? " ending {$this->last4}" : ''));
    }
}
