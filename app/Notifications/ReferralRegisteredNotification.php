<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReferralRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $newUser,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('¡Alguien usó tu código de referido!')
            ->greeting("Hola {$notifiable->name},")
            ->line("**{$this->newUser->name}** ({$this->newUser->email}) acaba de registrarse usando tu código de referido.")
            ->line('Tan pronto realice su primer depósito, recibirás una comisión por hacer más grande nuestro Nodo.')
            ->action('Ver mis referidos', config('app.frontend_url', config('app.url')) . '/referrals');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'  => 'referral_registered',
            'title' => '¡Nuevo referido registrado!',
            'body'  => "{$this->newUser->name} se unió con tu código. Recibirás comisión en su primer depósito.",
            'url'   => '/referrals',
            'meta'  => [
                'referred_user_id'   => $this->newUser->id,
                'referred_user_name' => $this->newUser->name,
            ],
        ];
    }
}
