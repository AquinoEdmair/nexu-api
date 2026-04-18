<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DepositConfirmedNotification extends Notification
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
        $net      = number_format((float) $this->transaction->net_amount, 2);
        $gross    = number_format((float) $this->transaction->amount, 2);
        $fee      = number_format((float) $this->transaction->fee_amount, 2);
        $currency = $this->transaction->currency;

        return (new MailMessage())
            ->subject("Depósito de \${$net} {$currency} confirmado")
            ->greeting("Hola {$notifiable->name},")
            ->line("Tu depósito de **\${$gross} {$currency}** ha sido confirmado.")
            ->line("Se descontó una comisión de **\${$fee} {$currency}**. Se acreditaron **\${$net} {$currency}** a tu saldo en operación.")
            ->action('Ver mi cuenta', config('app.frontend_url', config('app.url')));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $net      = number_format((float) $this->transaction->net_amount, 2);
        $currency = $this->transaction->currency;

        return [
            'type'  => 'deposit_confirmed',
            'title' => 'Depósito confirmado',
            'body'  => "+\${$net} {$currency} acreditado a tu saldo en operación.",
            'url'   => '/dashboard',
            'meta'  => [
                'transaction_id' => $this->transaction->id,
                'net_amount'     => $this->transaction->net_amount,
                'currency'       => $currency,
            ],
        ];
    }
}
