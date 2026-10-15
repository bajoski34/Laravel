<?php

declare(strict_types=1);

namespace Flutterwave\Payments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * A customer's bank has raised a chargeback that needs a response before its due date.
 *
 *     Notification::route('mail', 'disputes@shop.test')->notify(new ChargebackRaised($event->data));
 *
 * Or set FLW_NOTIFY_CHARGEBACKS and the package sends it for every new chargeback.
 */
class ChargebackRaised extends Notification
{
    use Queueable;

    /**
     * @param  array  $chargeback  the chargeback from a ChargebackUpdated webhook or chargebacks()->all()
     * @param  array  $channels  e.g. ['mail', 'database']
     */
    public function __construct(public array $chargeback, public array $channels = ['mail']) {}

    public function via(mixed $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $reference = $this->chargeback['tx_ref'] ?? $this->chargeback['flw_ref'] ?? "#{$this->chargeback['id']}";
        $due = $this->due();

        $message = (new MailMessage)
            ->subject("Chargeback on {$reference}: respond by {$due}")
            ->line("A customer's bank has disputed payment {$reference}".(isset($this->chargeback['amount']) ? ' for '.number_format((float) $this->chargeback['amount'], 2) : '').'.')
            ->line('Respond by '.$due.', or the customer is refunded automatically.');

        if (! empty($this->chargeback['comment'])) {
            $message->line('Reason given: '.$this->chargeback['comment']);
        }

        $message->line('Accept it if the customer didn\'t get what they paid for. If they did, dispute it with your evidence: one JPEG, PNG or PDF under 8MB.');

        return Route::has('flutterwave.admin')
            ? $message->action('Respond to the chargeback', route('flutterwave.admin'))
            : $message->line('Respond on your Flutterwave dashboard, or with php artisan flutterwave:decline-chargeback.');
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'chargeback_id' => $this->chargeback['id'] ?? null,
            'tx_ref' => $this->chargeback['tx_ref'] ?? null,
            'flw_ref' => $this->chargeback['flw_ref'] ?? null,
            'amount' => $this->chargeback['amount'] ?? null,
            'due_date' => $this->chargeback['due_date'] ?? $this->chargeback['due_at'] ?? null,
            'comment' => $this->chargeback['comment'] ?? null,
        ];
    }

    private function due(): string
    {
        $due = strtotime((string) ($this->chargeback['due_date'] ?? $this->chargeback['due_at'] ?? ''));

        return $due ? date('j M Y, H:i T', $due) : 'the due date';
    }
}
