<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

final class CampaignNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Campaign $campaign
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->campaign->title);

        if ($this->campaign->image_url) {
            $message->line(new HtmlString('<div style="text-align: center; margin-bottom: 20px;"><img src="'.htmlspecialchars($this->campaign->image_url).'" alt="'.htmlspecialchars($this->campaign->title).'" style="max-width: 100%; height: auto; border-radius: 8px;"></div>'));
        }

        if ($this->campaign->description) {
            $message->line(new HtmlString($this->campaign->description));
        }

        if ($this->campaign->cta_type === 'redirect' && $this->campaign->cta_url) {
            $message->action($this->campaign->cta_text ?? 'Acceder', $this->campaign->cta_url);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'title'       => $this->campaign->title,
        ];
    }
}
