<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $type,
        private readonly string $mailSubject,
        private readonly string $title,
        private readonly string $body,
        private readonly string $actionUrl,
        private readonly string $actionLabel = 'Ver en el panel',
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage();
        $mail->subject($this->mailSubject . ' — NEXU Admin')
            ->greeting("Hola {$notifiable->name},")
            ->line($this->body)
            ->action($this->actionLabel, $this->actionUrl);

        $adminEmail = SystemSetting::get('admin_notification_email');
        if ($adminEmail && $notifiable->email !== $adminEmail) {
            $mail->bcc($adminEmail);
        }

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'  => $this->type,
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->actionUrl,
        ];
    }
}
