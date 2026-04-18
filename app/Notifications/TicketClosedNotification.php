<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketClosedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketId = strtoupper(substr($this->ticket->id, 0, 8));

        return (new MailMessage())
            ->subject("Ticket #{$ticketId} cerrado — {$this->ticket->subject}")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu ticket **#{$ticketId} — {$this->ticket->subject}** ha sido marcado como resuelto y cerrado por nuestro equipo de soporte.")
            ->line('Si el problema persiste o tienes alguna otra consulta, puedes abrir un nuevo ticket desde tu dashboard.')
            ->action('Abrir nuevo ticket', config('app.frontend_url', config('app.url')) . '/support');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $ticketId = strtoupper(substr($this->ticket->id, 0, 8));

        return [
            'type'  => 'ticket_closed',
            'title' => "Ticket #{$ticketId} cerrado",
            'body'  => "Tu ticket — {$this->ticket->subject} — ha sido resuelto y cerrado.",
            'url'   => '/support',
            'meta'  => ['ticket_id' => $this->ticket->id, 'subject' => $this->ticket->subject],
        ];
    }
}
