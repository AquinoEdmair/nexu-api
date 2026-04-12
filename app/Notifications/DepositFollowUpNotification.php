<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DepositInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DepositFollowUpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DepositInvoice $invoice,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount   = number_format((float) $this->invoice->amount_expected, 2);
        $currency = $this->invoice->currency;

        return (new MailMessage())
            ->subject("¿Tuviste algún inconveniente con tu depósito de \${$amount} {$currency}?")
            ->greeting("Hola {$notifiable->name},")
            ->line("Notamos que iniciaste un depósito de **\${$amount} {$currency}** pero aún no hemos recibido la confirmación.")
            ->line('Si ya realizaste la transferencia, puede tardar unos minutos en reflejarse.')
            ->line('Si tuviste algún problema o inconveniente, estamos aquí para ayudarte.')
            ->action('Completar mi depósito', config('app.frontend_url', config('app.url')))
            ->line('Si no iniciaste este proceso, puedes ignorar este mensaje.');
    }
}
