<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminPasswordResetNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/admin/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]));

        return (new MailMessage())
            ->subject('Restablecer contraseña — NEXU Admin')
            ->line('Recibiste este correo porque se solicitó un restablecimiento de contraseña para tu cuenta de administrador.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace expirará en 60 minutos.')
            ->line('Si no solicitaste este restablecimiento, ignora este correo.');
    }
}
