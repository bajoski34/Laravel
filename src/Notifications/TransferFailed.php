<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A payout failed, so the recipient wasn't paid.
 *
 *     Notification::route('mail', 'finance@shop.test')->notify(new TransferFailed($event->data));
 *
 * Or set FLW_NOTIFY_FAILED_TRANSFERS and the package sends it for every failed transfer.
 */
class TransferFailed extends Notification
{
    use Queueable;

    /**
     * @param  array  $transfer  the transfer from a TransferCompleted webhook or transfers()->find()
     */
    public function __construct(public array $transfer, public array $channels = ['mail']) {}

    public function via(mixed $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $reference = $this->transfer['reference'] ?? "#{$this->transfer['id']}";
        $amount = trim(($this->transfer['currency'] ?? '').' '.(isset($this->transfer['amount']) ? number_format((float) $this->transfer['amount'], 2) : ''));
        $recipient = trim(($this->transfer['full_name'] ?? '').' '.(isset($this->transfer['account_number']) ? "({$this->transfer['account_number']})" : ''));

        return (new MailMessage)
            ->error()
            ->subject("Payout {$reference} failed")
            ->line("The {$amount} payout".($recipient !== '' ? " to {$recipient}" : '').' failed, so the recipient wasn\'t paid.')
            ->line('Flutterwave said: '.($this->transfer['complete_message'] ?? 'no reason given.'))
            ->line("Check the recipient's details, then retry it with Flutterwave::transfers()->retry({$this->transfer['id']}).");
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer['id'] ?? null,
            'reference' => $this->transfer['reference'] ?? null,
            'amount' => $this->transfer['amount'] ?? null,
            'currency' => $this->transfer['currency'] ?? null,
            'reason' => $this->transfer['complete_message'] ?? null,
        ];
    }
}
