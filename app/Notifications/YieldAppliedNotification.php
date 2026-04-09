<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\YieldLogUser;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class YieldAppliedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly YieldLogUser $yieldLogUser,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (! empty($notifiable->phone)) {
            $channels[] = 'vonage';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount          = (float) $this->yieldLogUser->amount_applied;
        $balanceAfter    = number_format((float) $this->yieldLogUser->balance_after, 2);
        $amountFormatted = number_format(abs($amount), 2);

        [$subject, $line] = $amount >= 0
            ? [
                "Rendimiento de \${$amountFormatted} aplicado a tu cuenta",
                "Se ha aplicado un rendimiento de \${$amountFormatted} USD a tu saldo en operación.",
            ]
            : [
                "Ajuste de -\${$amountFormatted} aplicado a tu cuenta",
                "Se ha realizado un ajuste de -\${$amountFormatted} USD a tu saldo en operación.",
            ];

        return (new MailMessage())
            ->subject($subject)
            ->greeting("Hola {$notifiable->name},")
            ->line($line)
            ->line("Tu saldo en operación es ahora \${$balanceAfter} USD.")
            ->action('Ver mi cuenta', config('app.frontend_url', config('app.url')));
    }

    public function toVonage(object $notifiable): \Illuminate\Notifications\Messages\VonageMessage
    {
        $amount  = (float) $this->yieldLogUser->amount_applied;
        $balance = number_format((float) $this->yieldLogUser->balance_after, 2);
        $abs     = number_format(abs($amount), 2);

        $text = $amount >= 0
            ? "NEXU: Rendimiento de \${$abs} aplicado. Saldo en operación: \${$balance}."
            : "NEXU: Ajuste de -\${$abs} aplicado. Saldo en operación: \${$balance}.";

        return (new \Illuminate\Notifications\Messages\VonageMessage())->content($text);
    }
}
