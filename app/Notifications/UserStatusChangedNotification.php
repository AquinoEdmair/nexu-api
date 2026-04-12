<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UserStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $newStatus,
        private readonly string $reason,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isBlocked = $this->newStatus === 'blocked';

        return (new MailMessage())
            ->subject($isBlocked ? 'Tu cuenta NEXU ha sido suspendida' : 'Tu cuenta NEXU ha sido reactivada')
            ->greeting("Hola {$notifiable->name},")
            ->line($isBlocked
                ? 'Tu cuenta ha sido suspendida temporalmente.'
                : 'Tu cuenta ha sido reactivada y puedes volver a operar con normalidad.'
            )
            ->when($isBlocked, fn(MailMessage $m) => $m->line("**Motivo:** {$this->reason}"))
            ->line($isBlocked
                ? 'Si tienes dudas, contacta a soporte.'
                : '¡Bienvenido de vuelta!'
            )
            ->action('Ir a NEXU', config('app.frontend_url', config('app.url')));
    }
}
