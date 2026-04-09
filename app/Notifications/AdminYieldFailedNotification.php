<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\YieldLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminYieldFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly YieldLog $yieldLog,
        private readonly string   $errorMessage,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appliedAt  = $this->yieldLog->applied_at?->format('d/m/Y H:i') ?? '—';
        $valueLabel = $this->yieldLog->type === 'percentage'
            ? number_format((float) $this->yieldLog->value, 4) . '%'
            : '$' . number_format((float) $this->yieldLog->value, 2);

        return (new MailMessage())
            ->subject('Error en aplicación de rendimiento — NEXU')
            ->error()
            ->greeting("Hola {$notifiable->name},")
            ->line("El rendimiento de **{$valueLabel}** iniciado el **{$appliedAt}** ha fallado.")
            ->line("**Error:** {$this->errorMessage}")
            ->line('Revisa el detalle en el panel para investigar el estado de cada usuario.')
            ->action('Ver detalle en el panel', route('filament.admin.resources.yield-logs.view', $this->yieldLog));
    }
}
