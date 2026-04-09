<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WithdrawalCompletedNotification extends Notification
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
        $txHash   = $this->request->tx_hash ?? '—';

        return (new MailMessage())
            ->subject("Tu retiro de \${$amount} {$currency} ha sido enviado")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu retiro de **\${$amount} {$currency}** ha sido procesado exitosamente.")
            ->line("**Hash de transacción:** `{$txHash}`")
            ->line('Los fondos deberían aparecer en tu dirección de destino en breve, según la red blockchain.')
            ->action('Ver historial', config('app.frontend_url', config('app.url')));
    }
}
