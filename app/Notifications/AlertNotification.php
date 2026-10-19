<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A plain in-app (bell) notification: a message and an optional link.
 * Used by TripNotifier and the scheduled reminder commands.
 */
class AlertNotification extends Notification
{
    public function __construct(
        protected string $type,
        protected string $message,
        protected ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
