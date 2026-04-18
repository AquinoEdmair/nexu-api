<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ElitePoint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PointsAdjustedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ElitePoint $elitePoint,
        private readonly string $reason,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $points   = (float) $this->elitePoint->points;
        $positive = $points >= 0;
        $formatted = number_format(abs($points), 0);

        [$subject, $line] = $positive
            ? [
                "Se te han añadido {$formatted} puntos Elite",
                "Se han acreditado **{$formatted} puntos Elite** a tu cuenta.",
            ]
            : [
                "Se han deducido {$formatted} puntos Elite",
                "Se han deducido **{$formatted} puntos Elite** de tu cuenta.",
            ];

        return (new MailMessage())
            ->subject($subject)
            ->greeting("Hola {$notifiable->name},")
            ->line($line)
            ->line("**Motivo:** {$this->reason}")
            ->action('Ver mi cuenta', config('app.frontend_url', config('app.url')));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $points   = (float) $this->elitePoint->points;
        $positive = $points >= 0;
        $formatted = number_format(abs($points), 0);

        return [
            'type'  => 'points_adjusted',
            'title' => $positive ? 'Puntos Elite añadidos' : 'Puntos Elite deducidos',
            'body'  => ($positive ? '+' : '-') . $formatted . ' puntos Elite. ' . $this->reason,
            'url'   => '/rewards',
            'meta'  => [
                'elite_point_id' => $this->elitePoint->id,
                'points'         => $this->elitePoint->points,
                'reason'         => $this->reason,
            ],
        ];
    }
}
