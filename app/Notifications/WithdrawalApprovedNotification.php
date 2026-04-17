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

        $address = $this->request->destination_address;
        $net     = number_format((float) $this->request->net_amount, 2);

        return (new MailMessage())
            ->subject("Tu retiro de \${$amount} {$currency} ha sido aprobado")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu solicitud de retiro por **\${$amount} {$currency}** ha sido aprobada y está siendo procesada.")
            ->line("El monto neto de **\${$net} {$currency}** será enviado a la dirección: `{$address}`")
            ->line('El proceso puede tomar **hasta 48 horas hábiles** desde la aprobación.')
            ->line('Si tienes alguna duda o necesitas más información, puedes contactarnos respondiendo este correo o a través del soporte en la plataforma.')
            ->action('Ver estado en la app', config('app.frontend_url', config('app.url')));
    }
}
