<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketId = strtoupper(substr($this->ticket->id, 0, 8));

        return (new MailMessage())
            ->subject("Ticket #{$ticketId} recibido — {$this->ticket->subject}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Hemos recibido tu solicitud de soporte con el asunto: **{$this->ticket->subject}**")
            ->line("Tu ticket ha sido registrado con el ID **#{$ticketId}** y será atendido en un máximo de **48 horas hábiles** (GMT-4).")
            ->line('Puedes seguir el estado de tu ticket y responder directamente desde tu dashboard en la plataforma.')
            ->line('Si necesitas información adicional urgente, puedes responder a este correo.')
            ->action('Ver mi ticket', config('app.frontend_url', config('app.url')) . '/support');
    }
}
