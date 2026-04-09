<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WithdrawalRejectedNotification extends Notification
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
        $reason   = $this->request->rejection_reason ?? '—';

        return (new MailMessage())
            ->subject("Tu retiro de \${$amount} {$currency} fue rechazado")
            ->error()
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu solicitud de retiro por **\${$amount} {$currency}** ha sido rechazada.")
            ->line("**Motivo:** {$reason}")
            ->line('Los fondos han sido devueltos a tu saldo disponible.')
            ->action('Ver mi cuenta', config('app.frontend_url', config('app.url')));
    }
}
