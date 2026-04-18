<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BalanceAdjustedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Transaction $transaction,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $delta    = (float) $this->transaction->amount;
        $positive = $delta >= 0;
        $formatted = number_format(abs($delta), 2);
        $reason   = $this->transaction->description ?? '—';

        [$subject, $line] = $positive
            ? [
                "Ajuste de +\${$formatted} USD a tu saldo",
                "Se ha acreditado un ajuste de **+\${$formatted} USD** a tu saldo en operación.",
            ]
            : [
                "Ajuste de -\${$formatted} USD a tu saldo",
                "Se ha realizado un ajuste de **-\${$formatted} USD** a tu saldo en operación.",
            ];

        return (new MailMessage())
            ->subject($subject)
            ->greeting("Hola {$notifiable->name},")
            ->line($line)
            ->line("**Motivo:** {$reason}")
            ->action('Ver mi cuenta', config('app.frontend_url', config('app.url')));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $delta    = (float) $this->transaction->amount;
        $positive = $delta >= 0;
        $formatted = number_format(abs($delta), 2);

        return [
            'type'  => 'balance_adjusted',
            'title' => $positive ? 'Ajuste de saldo positivo' : 'Ajuste de saldo',
            'body'  => ($positive ? '+$' : '-$') . $formatted . ' USD ajustado a tu saldo en operación.',
            'url'   => '/dashboard',
            'meta'  => [
                'transaction_id' => $this->transaction->id,
                'amount'         => $this->transaction->amount,
                'reason'         => $this->transaction->description,
            ],
        ];
    }
}
