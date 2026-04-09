<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UserCreatedByAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tempPassword,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Bienvenido a NEXU — Tu cuenta ha sido creada')
            ->greeting("Hola {$notifiable->name},")
            ->line('Un administrador ha creado tu cuenta en NEXU.')
            ->line('Tus credenciales de acceso son:')
            ->line("**Email:** {$notifiable->email}")
            ->line("**Contraseña temporal:** {$this->tempPassword}")
            ->line('Por tu seguridad, cambia tu contraseña al ingresar por primera vez.')
            ->action('Ingresar a NEXU', config('app.frontend_url', config('app.url')))
            ->line('Si no solicitaste esta cuenta, ignora este mensaje.');
    }
}
