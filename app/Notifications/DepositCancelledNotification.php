<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DepositInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DepositCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly DepositInvoice $invoice,
        private readonly string $reason = 'Cancelado por el administrador',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database']; // Keeping mail off for now just like confirmed, as per previous fix
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gross = number_format((float) $this->invoice->amount_expected, 2);
        $currency = $this->invoice->currency;

        return (new MailMessage())
            ->subject("Tu depósito ha sido cancelado — NEXU")
            ->greeting("Hola {$notifiable->name},")
            ->line("Te informamos que tu solicitud de depósito por **\${$gross} {$currency}** ha sido cancelada.")
            ->line("Motivo: **{$this->reason}**")
            ->line('Si crees que esto es un error o necesitas ayuda, por favor contacta a soporte.')
            ->action('Ver mi panel', config('app.frontend_url', config('app.url')));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $gross = number_format((float) $this->invoice->amount_expected, 2);
        $currency = $this->invoice->currency;

        return [
            'type' => 'deposit_cancelled',
            'title' => 'Depósito cancelado',
            'body' => "Tu depósito de \${$gross} {$currency} fue cancelado. Motivo: {$this->reason}",
            'url' => '/dashboard',
            'meta' => [
                'invoice_id' => $this->invoice->invoice_id,
                'amount_expected' => $this->invoice->amount_expected,
                'currency' => $currency,
            ],
        ];
    }
}
