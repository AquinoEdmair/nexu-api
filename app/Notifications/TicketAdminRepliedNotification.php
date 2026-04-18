<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TicketAdminRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly string $replyBody,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticketId = strtoupper(substr($this->ticket->id, 0, 8));
        $preview  = mb_substr($this->replyBody, 0, 200) . (mb_strlen($this->replyBody) > 200 ? '...' : '');

        return (new MailMessage())
            ->subject("Nueva respuesta en tu ticket #{$ticketId}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El equipo de soporte ha respondido a tu ticket **#{$ticketId} — {$this->ticket->subject}**:")
            ->line("> {$preview}")
            ->line('Puedes responder directamente desde tu dashboard.')
            ->action('Ver ticket', config('app.frontend_url', config('app.url')) . '/support');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $ticketId = strtoupper(substr($this->ticket->id, 0, 8));
        $preview  = mb_substr($this->replyBody, 0, 100) . (mb_strlen($this->replyBody) > 100 ? '...' : '');

        return [
            'type'  => 'ticket_replied',
            'title' => "Respuesta en ticket #{$ticketId}",
            'body'  => $preview,
            'url'   => '/support',
            'meta'  => ['ticket_id' => $this->ticket->id, 'subject' => $this->ticket->subject],
        ];
    }
}
