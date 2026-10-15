<?php

declare(strict_types=1);

namespace Flutterwave\Payments;

use DateTimeImmutable;
use DateTimeInterface;
use Flutterwave\Payments\Data\SavedCard;
use Flutterwave\Payments\Exception\InvalidArgument;

/**
 * Save a customer's card after their first payment and charge it again later.
 *
 * Add the columns with `php artisan flutterwave:install --billable`, then:
 *
 *     $user->saveFlutterwaveCard($event->transaction);   // in a ChargeCompleted listener
 *     $user->chargeSavedCard(5000);                      // next month
 *
 * The token is stored encrypted and hidden from arrays and JSON (including Inertia props).
 */
trait Billable
{
    public function initializeBillable(): void
    {
        $this->mergeCasts([
            'flutterwave_card_token' => 'encrypted',
            'flutterwave_card_saved_at' => 'datetime',
        ]);

        $this->makeHidden(['flutterwave_card_token', 'flutterwave_card_email']);
    }

    /**
     * Save the card from a verified card payment. Only successful card payments can be saved.
     */
    public function saveFlutterwaveCard(array $transaction): SavedCard
    {
        if (($transaction['status'] ?? null) !== 'successful') {
            throw new InvalidArgument('Only cards from successful payments can be saved.');
        }

        $card = SavedCard::fromTransaction($transaction);

        $this->forceFill([
            'flutterwave_card_token' => $card->token,
            'flutterwave_card_email' => $card->email,
            'flutterwave_card_last4' => $card->last4,
            'flutterwave_card_type' => $card->type,
            'flutterwave_card_expiry' => $card->expiry,
            'flutterwave_card_saved_at' => $card->savedAt,
        ])->save();

        return $card;
    }

    public function hasFlutterwaveCard(): bool
    {
        return ! empty($this->flutterwave_card_token);
    }

    public function flutterwaveCard(): ?SavedCard
    {
        if (! $this->hasFlutterwaveCard()) {
            return null;
        }

        $savedAt = $this->flutterwave_card_saved_at;

        return new SavedCard(
            (string) $this->flutterwave_card_token,
            (string) $this->flutterwave_card_email,
            $this->flutterwave_card_last4,
            $this->flutterwave_card_type,
            $this->flutterwave_card_expiry,
            $savedAt instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($savedAt) : null,
        );
    }

    public function forgetFlutterwaveCard(): void
    {
        $this->forceFill([
            'flutterwave_card_token' => null,
            'flutterwave_card_email' => null,
            'flutterwave_card_last4' => null,
            'flutterwave_card_type' => null,
            'flutterwave_card_expiry' => null,
            'flutterwave_card_saved_at' => null,
        ])->save();
    }

    /**
     * Charge the saved card. Check `data.status` on the response: it can be pending while
     * the customer approves the charge with 3-D Secure.
     */
    public function chargeSavedCard(int|float|string $amount, array $options = []): array
    {
        $card = $this->savedCardOrFail();

        return app('flutterwave')->cards()->charge($card->token, $card->email, $amount, $options);
    }

    /**
     * Hold $amount on the saved card; settle it with Flutterwave::preauth().
     */
    public function preauthorizeSavedCard(int|float|string $amount, array $options = []): array
    {
        $card = $this->savedCardOrFail();

        return app('flutterwave')->cards()->preauthorize($card->token, $card->email, $amount, $options);
    }

    private function savedCardOrFail(): SavedCard
    {
        return $this->flutterwaveCard() ?? throw new InvalidArgument('This customer has no saved card. Save one from a verified payment with saveFlutterwaveCard().');
    }
}
