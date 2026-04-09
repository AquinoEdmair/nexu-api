<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WithdrawalApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly WithdrawalRequest $request,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount   = number_format((float) $this->request->amount, 2);
        $currency = $this->request->currency;

        return (new MailMessage())
            ->subject("Tu retiro de \${$amount} {$currency} ha sido aprobado")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu solicitud de retiro por **\${$amount} {$currency}** ha sido aprobada.")
            ->line('Estamos procesando el envío a tu dirección de destino. Recibirás otra notificación cuando se complete.')
            ->action('Ver estado en la app', config('app.frontend_url', config('app.url')));
    }
}
