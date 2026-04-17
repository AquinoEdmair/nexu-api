<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WithdrawalCreatedNotification extends Notification
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
        $net      = number_format((float) $this->request->net_amount, 2);
        $currency = $this->request->currency;
        $address  = $this->request->destination_address;

        return (new MailMessage())
            ->subject("Solicitud de retiro de \${$amount} {$currency} recibida")
            ->greeting("Hola {$notifiable->name},")
            ->line("Hemos recibido tu solicitud de retiro por **\${$amount} {$currency}**.")
            ->line("Una vez aprobada, recibirás **\${$net} {$currency}** en la dirección: `{$address}`")
            ->line('Tu solicitud será revisada y procesada en un plazo máximo de **48 horas hábiles**.')
            ->line('Si necesitas más información o tienes alguna duda, puedes contactarnos respondiendo este correo o a través del soporte en la plataforma.')
            ->action('Ver estado en la app', config('app.frontend_url', config('app.url')));
    }
}
