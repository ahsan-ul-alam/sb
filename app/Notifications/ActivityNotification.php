<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ActivityNotification extends Notification
{
    public function __construct(private string $title, private string $body, private ?int $requestId = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'request_id' => $this->requestId];
    }
}
