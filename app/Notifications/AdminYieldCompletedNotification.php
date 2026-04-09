<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\YieldLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminYieldCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly YieldLog $yieldLog,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalApplied = number_format((float) $this->yieldLog->total_applied, 2);
        $usersCount   = $this->yieldLog->users_count;
        $appliedAt    = $this->yieldLog->applied_at?->format('d/m/Y H:i') ?? '—';
        $completedAt  = $this->yieldLog->completed_at?->format('d/m/Y H:i') ?? '—';
        $valueLabel   = $this->yieldLog->type === 'percentage'
            ? number_format((float) $this->yieldLog->value, 4) . '%'
            : '$' . number_format((float) $this->yieldLog->value, 2);

        return (new MailMessage())
            ->subject('Rendimiento aplicado correctamente — NEXU')
            ->greeting("Hola {$notifiable->name},")
            ->line("El rendimiento de **{$valueLabel}** ha sido procesado exitosamente.")
            ->line("**Usuarios afectados:** {$usersCount}")
            ->line("**Monto total distribuido:** \${$totalApplied} USD")
            ->line("**Iniciado el:** {$appliedAt}")
            ->line("**Completado el:** {$completedAt}")
            ->action('Ver detalle en el panel', route('filament.admin.resources.yield-logs.view', $this->yieldLog));
    }
}
