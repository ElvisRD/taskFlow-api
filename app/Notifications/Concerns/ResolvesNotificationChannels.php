<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\NotificationType;

trait ResolvesNotificationChannels
{
    abstract public function notificationType(): NotificationType;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database', 'mail', 'broadcast'];
        }

        return $notifiable->notificationChannels($this->notificationType(), $this->task);
    }
}
