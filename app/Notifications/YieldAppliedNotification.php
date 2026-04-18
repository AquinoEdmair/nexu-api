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
        return ['mail', 'database'];
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

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $amount = (float) $this->yieldLogUser->amount_applied;
        $positive = $amount >= 0;

        return [
            'type'  => 'yield_applied',
            'title' => $positive ? 'Rendimiento aplicado' : 'Ajuste de rendimiento',
            'body'  => ($positive ? '+$' : '-$') . number_format(abs($amount), 2) . ' USD aplicado a tu saldo en operación.',
            'url'   => '/yields',
            'meta'  => ['amount' => $this->yieldLogUser->amount_applied, 'balance_after' => $this->yieldLogUser->balance_after],
        ];
    }
}
